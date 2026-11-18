import { expect, test, type Page } from '@playwright/test';

/**
 * PHASE 5 — Role-based testing for demo accounts.
 * Verifies each role can authenticate and sees role-appropriate admin shell content.
 */

const DEMO_ACCOUNTS = [
  { role: 'super_admin', email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  { role: 'admin', email: 'admin@taisartownhub.com', password: 'Admin@Taisar2026' },
  { role: 'manager', email: 'manager@taisartownhub.com', password: 'Manager@2026' },
  { role: 'dealer', email: 'dealer@taisartownhub.com', password: 'Dealer@2026' },
  { role: 'operator', email: 'operator@taisartownhub.com', password: 'Operator@2026' },
] as const;

async function loginAs(page: Page, account: (typeof DEMO_ACCOUNTS)[number]) {
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

test.describe('Role-Based Access - Every role gets correct permissions', () => {
  test.describe('Super Admin - Full platform access', () => {
    test('can access key admin sections', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[0]);

      await expect(page.getByRole('button', { name: /dashboard overview/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /security & roles/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /audit & recycle bin/i }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /agency approvals/i }).first()).toBeVisible();

      await page.getByRole('button', { name: /security & roles/i }).first().click();
      await expect(page.locator('main')).toContainText(/security|role|user|permission/i);

      await page.getByRole('button', { name: /audit & recycle bin/i }).first().click();
      await expect(page.locator('main')).toContainText(/audit|log|recycle|action/i);

      await page.getByRole('button', { name: /agency approvals/i }).first().click();
      await expect(page.locator('main')).toContainText(/agency|approval|verify|status|plan|subscription/i);
    });

    test('can access agency approval workspace', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[0]);
      await page.getByRole('button', { name: /agency approvals/i }).first().click();
      await expect(page.locator('main')).toContainText(/agency/i);
    });
  });

  test.describe('Agency Admin - Agency-scoped access', () => {
    test('can access core admin dashboard', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[1]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
      await expect(page.getByRole('button', { name: /dashboard overview/i }).first()).toBeVisible();
    });
  });

  test.describe('Manager - Scoped access', () => {
    test('can access admin dashboard shell', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[2]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Dealer - Scoped access', () => {
    test('can access admin dashboard shell', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[3]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Operator - Limited access', () => {
    test('can access admin dashboard shell', async ({ page }) => {
      await loginAs(page, DEMO_ACCOUNTS[4]);
      await expect(page).toHaveURL(/\/admin/);
      await expect(page.locator('main')).toBeVisible();
    });
  });

  test.describe('Visitor / Guest - Public access only', () => {
    test('cannot access admin dashboard', async ({ page }) => {
      await page.goto('/admin');
      await page.waitForTimeout(2500);
      const hasSignIn = (await page.locator('text=/sign in|login/i').count()) > 0;
      const isLoginPage = /\/login|\/admin-login/.test(page.url());
      expect(hasSignIn || isLoginPage).toBe(true);
    });

    test('can browse key public pages', async ({ page }) => {
      await page.goto('/');
      await expect(page.locator('main')).toBeVisible();

      await page.getByRole('button', { name: /^properties$/i }).first().click();
      await expect(page).toHaveURL(/\/properties/, { timeout: 10000 });
      await expect(page.locator('main')).toBeVisible();

      await page.getByRole('button', { name: /^agencies$/i }).first().click();
      await expect(page).toHaveURL(/\/agencies/, { timeout: 10000 });
      await expect(page.locator('main')).toBeVisible();
    });
  });
});
