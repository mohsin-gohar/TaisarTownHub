import { expect, test, type Page } from '@playwright/test';

/**
 * PHASE 6 — End-to-End Business Workflow Testing.
 */

const SUPER_ADMIN = {
  email: 'superadmin@taisartownhub.com',
  password: 'Super@Admin2026!',
};
const API_BASE = process.env.PLAYWRIGHT_API_URL || process.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

async function loginToAdmin(page: Page) {
  await page.goto('/admin-login');
  const emailInput = page.getByRole('textbox', { name: /account email|admin@taisartownhub/i });
  const passwordInput = page.locator('input[type="password"]').first();
  await expect(emailInput).toBeVisible({ timeout: 15000 });
  await emailInput.fill(SUPER_ADMIN.email);
  await passwordInput.fill(SUPER_ADMIN.password);
  await passwordInput.press('Enter');
  await expect(page).toHaveURL(/\/admin(\/.*)?$/, { timeout: 20000 });
  await expect(page.locator('main')).toBeVisible();
}

test.describe('Business Workflows', () => {
  test('Workflow: Visitor → Browse → Contact', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();

    await page.getByRole('button', { name: /^properties$/i }).first().click();
    await expect(page).toHaveURL(/\/properties/, { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();

    await page.getByRole('button', { name: /^contact$/i }).first().click();
    await expect(page).toHaveURL(/\/contact/, { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();
  });

  test('Workflow: Login → Admin Dashboard', async ({ page }) => {
    await loginToAdmin(page);
    await expect(page).toHaveURL(/\/admin/);
  });

  test('Workflow: Protected route access after logout flow is blocked', async ({ page, context }) => {
    await loginToAdmin(page);
    await expect(page).toHaveURL(/\/admin/);

    await context.clearCookies();
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });
    await page.goto('/admin');
    await page.waitForTimeout(2000);
    const hasSignIn = (await page.locator('text=/sign in|login/i').count()) > 0;
    const isLoginPage = /\/login|\/admin-login/.test(page.url());
    expect(hasSignIn || isLoginPage).toBe(true);
  });

  test('API: Health check responds', async ({ request }) => {
    const response = await request.get(`${API_BASE}/health`);
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(['healthy', 'ok']).toContain(body.status);
  });

  test('API: Catalog responds', async ({ request }) => {
    const response = await request.get(`${API_BASE}/catalog`);
    expect(response.status()).toBe(200);
  });
});
