import { expect, type APIRequestContext, type Page } from '@playwright/test';

/**
 * Sign in through the real backend API, then load the admin SPA with the
 * issued token. This is used for role matrices where many accounts must sign
 * in quickly: driving the browser form for every role exhausts the 10/min
 * shared-IP auth limiter and produces failures unrelated to the role itself.
 *
 * The Node-side request can carry a TEST-NET-3 `X-Forwarded-For` without
 * triggering a browser CORS preflight, and the backend trusts that header
 * only because the test client itself is 127.0.0.1 (TRUSTED_PROXIES).
 *
 * The actual sign-in form keeps its dedicated coverage in admin-pages.spec.ts
 * (one real form login) and in interactive/security specs (invalid input).
 */
const API_BASE =
  process.env.PLAYWRIGHT_API_URL ||
  process.env.VITE_API_URL ||
  'http://127.0.0.1:8080/api';

/** Stable, non-routable IP per demo role; repeat runs stay under 10/min. */
export const DEMO_ROLE_IPS: Record<string, string> = {
  super_admin: '203.0.113.11',
  admin: '203.0.113.12',
  manager: '203.0.113.13',
  dealer: '203.0.113.14',
  operator: '203.0.113.15',
};

export interface DemoAccount {
  email: string;
  password: string;
  role?: string;
}

export async function loginToAdminViaApi(
  page: Page,
  request: APIRequestContext,
  account: DemoAccount,
  ip: string,
): Promise<void> {
  const response = await request.post(`${API_BASE}/auth/login`, {
    headers: { 'X-Forwarded-For': ip, Accept: 'application/json' },
    data: { email: account.email, password: account.password },
  });
  expect(response.ok(), `API login failed for ${account.email}: ${response.status()}`).toBeTruthy();

  const body = await response.json();
  const token = body.token as string;
  expect(token).toBeTruthy();

  // When the SPA and API share an origin (the Docker integration stack), tag
  // browser API requests with the same per-role TEST-NET address used by the
  // Node-side login. CI nginx trusts only its private Docker bridge for this
  // header; production nginx continues to use the actual remote address.
  const pageOrigin = new URL(process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:4173').origin;
  if (pageOrigin === new URL(API_BASE).origin) {
    await page.route('**/api/**', (route) => route.continue({
      headers: { ...route.request().headers(), 'X-Forwarded-For': ip },
    }));
  }

  // Inject the token BEFORE any application script runs on the first /admin
  // load. The previous double-navigation (boot without token -> set token ->
  // boot again) raced with the app's unauthenticated cleanup and occasionally
  // left the login screen instead of the admin shell under load. addInitScript
  // makes a single navigation deterministic; no assertion is relaxed.
  await page.addInitScript((value) => sessionStorage.setItem('tth_token', value), token);
  await page.goto('/admin', { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(/\/admin(\/.*)?$/, { timeout: 20000 });
  // Under parallel load the single-threaded local PHP dev server can take
  // several seconds to answer the workspace-verification calls that gate the
  // admin shell (the page shows "Verifying workspace access…" meanwhile). The
  // default 5s assertion timeout produces false negatives that do not occur
  // when the same tests run with one worker or against a real server.
  await expect(page.locator('main')).toBeVisible({ timeout: 20000 });
}
