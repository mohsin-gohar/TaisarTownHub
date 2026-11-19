import { expect, test } from '@playwright/test';

/**
 * G-13 — E2E smoke for the cold-load critical flows.
 *
 * Honest scope: this is the SMOKE layer only. The full 18-journey E2E
 * matrix is open work (see CODEBASE_AUDIT.md → G-13).
 */

const API_BASE = process.env.PLAYWRIGHT_API_URL || process.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

test('homepage renders the hero and public shell', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/Taiser Town|TaisarTownHub/i);
  await expect(page.locator('#root')).not.toBeEmpty();
});

test('login route renders the auth portal', async ({ page }) => {
  await page.goto('/login');
  // The luxury AuthPage is a full-page portal — a sign-in affordance must exist.
  await expect(page.locator('text=/sign in/i').first()).toBeVisible();
});

test('reset-password cold-loads OUTSIDE the SPA shell', async ({ page }) => {
  await page.goto('/reset-password?token=e2e-smoke-token&email=e2e@example.test');
  // The bridge renders the dedicated cold-load page, not the SPA (no
  // router-provided navbar; the utility shell shows the reset form).
  await expect(page.locator('text=/set a new password/i')).toBeVisible();
  await expect(page.locator('text=e2e@example.test')).toBeVisible();
});

test('verify-email cold-load page reports a bad token as failed', async ({ page }) => {
  await page.goto('/verify-email?email=e2e@example.test&token=not-a-real-token');
  await expect(
    page.locator('text=/verification failed|invalid/i').first(),
  ).toBeVisible({ timeout: 15_000 });
});

test('public share endpoint rejects an unknown well-formed token', async ({ request }) => {
  const token = 'e2e'.repeat(16); // 48 chars, matches the route pattern
  // Isolate this probe from the suite-wide guest bucket: 127.0.0.1 is the
  // trusted proxy in local/test, so TEST-NET-2 gets its own limiter counter.
  const response = await request.get(`${API_BASE}/shares/${token}/download`, {
    headers: { 'X-Forwarded-For': '198.51.100.88' },
  });
  expect(response.status()).toBe(410);
});
