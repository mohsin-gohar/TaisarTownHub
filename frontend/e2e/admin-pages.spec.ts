import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { DEMO_ROLE_IPS, loginToAdminViaApi } from './helpers/adminLogin';

const API_BASE = process.env.PLAYWRIGHT_API_URL || process.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

/**
 * PHASE 3 & 5 — Admin Pages & Role-Based Access.
 * Tests admin dashboard access for each demo role.
 */

const DEMO_ACCOUNTS = [
  { role: 'super_admin', email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  { role: 'admin', email: 'admin@taisartownhub.com', password: 'Admin@Taisar2026' },
  { role: 'manager', email: 'manager@taisartownhub.com', password: 'Manager@2026' },
  { role: 'dealer', email: 'dealer@taisartownhub.com', password: 'Dealer@2026' },
  { role: 'operator', email: 'operator@taisartownhub.com', password: 'Operator@2026' },
] as const;

/** One real browser-form sign-in keeps the auth UI itself under test. */
async function loginAsViaForm(page: Page, account: (typeof DEMO_ACCOUNTS)[number]) {
  await page.goto('/admin-login');
  const emailInput = page.getByRole('textbox', { name: /account email|admin@taisartownhub/i });
  const passwordInput = page.locator('input[type="password"]').first();
  await expect(emailInput).toBeVisible({ timeout: 15000 });
  await emailInput.fill(account.email);
  await passwordInput.fill(account.password);
  await passwordInput.press('Enter');
  await expect(page).toHaveURL(/\/admin(\/.*)?$/, { timeout: 20000 });
  await expect(page.locator('main')).toBeVisible();
}

test.describe('Admin Login - Every demo role can authenticate', () => {
  for (const account of DEMO_ACCOUNTS) {
    test(`${account.role} can reach admin dashboard`, async ({ page, request }) => {
      if (account.role === 'super_admin') {
        await loginAsViaForm(page, account);
      } else {
        // Node-side API sign-in with a per-role IP bucket. Eleven browser form
        // logins across this suite would exceed the 10/min shared-IP limiter.
        await loginToAdminViaApi(page, request, account, DEMO_ROLE_IPS[account.role]);
      }
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  }
});

test.describe('Unauthenticated Access - Protected routes blocked', () => {
  test('Guest accessing /admin shows login requirement', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForTimeout(2500);
    const hasSignIn = (await page.locator('text=/sign in|login/i').count()) > 0;
    const isLoginPage = /\/login|\/admin-login/.test(page.url());
    expect(hasSignIn || isLoginPage).toBe(true);
  });

  test('Guest accessing /admin/staff shows login requirement', async ({ page }) => {
    await page.goto('/admin/staff');
    await page.waitForTimeout(2500);
    const hasSignIn = (await page.locator('text=/sign in|login/i').count()) > 0;
    const isLoginPage = /\/login|\/admin-login/.test(page.url());
    expect(hasSignIn || isLoginPage).toBe(true);
  });
});

test.describe('Security - API protection', () => {
  test('Protected API endpoints require authentication', async ({ request }) => {
    const endpoints = [
      '/api/auth/me',
      '/api/agency/profile',
      '/api/admin/dashboard',
    ];
    for (const path of endpoints) {
      const response = await request.get(`${API_BASE.replace(/\/api$/, '')}${path}`);
      expect([401, 403]).toContain(response.status());
    }
  });

  test('Public API endpoints are accessible', async ({ request }) => {
    const response = await request.get(`${API_BASE}/health`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(['healthy', 'ok']).toContain(body.status);
  });

  test('Public catalog is accessible', async ({ request }) => {
    const response = await request.get(`${API_BASE}/catalog`);
    expect(response.status()).toBe(200);
  });
});
