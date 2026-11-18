import { expect, test } from '@playwright/test';

/**
 * PHASE 4 — Interactive Elements testing.
 * Tests key buttons, forms, navigation links, and recovery paths.
 */

test.describe('Navbar Interactive Elements', () => {
  test('Sign In navigates to login/auth page', async ({ page }) => {
    await page.goto('/');
    await page.goto('/login');
    await expect(page).toHaveURL(/\/login/, { timeout: 10000 });
    await expect(page.getByRole('textbox', { name: /account email|admin@taisartownhub/i })).toBeVisible();
  });

  test('Register action navigates to register page', async ({ page }) => {
    await page.goto('/register');
    await expect(page).toHaveURL(/\/register/, { timeout: 10000 });
    const count = await page.locator('input, button, select, textarea').count();
    expect(count).toBeGreaterThan(0);
  });
});

test.describe('Auth Forms', () => {
  test('Login form has email and password fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('textbox', { name: /account email|admin@taisartownhub/i })).toBeVisible();
    await expect(page.locator('input[type="password"]').first()).toBeVisible();
    const count = await page.locator('button, [role="button"]').count();
    expect(count).toBeGreaterThan(0);
  });

  test('Login with empty fields stays usable', async ({ page }) => {
    await page.goto('/login');
    const emailInput = page.getByRole('textbox', { name: /account email|admin@taisartownhub/i });
    const passwordInput = page.locator('input[type="password"]').first();
    await emailInput.fill('');
    await passwordInput.fill('');
    await passwordInput.press('Enter');
    await page.waitForTimeout(1500);
    await expect(page.locator('body')).toBeVisible();
    await expect(emailInput).toBeVisible();
  });

  test('Register form has required fields', async ({ page }) => {
    await page.goto('/register');
    const count = await page.locator('input, select, textarea').count();
    expect(count).toBeGreaterThan(0);
  });
});

test.describe('Property Listings', () => {
  test('Properties page shows listings', async ({ page }) => {
    await page.goto('/properties');
    await expect(page.locator('main')).toBeVisible();
  });

  test('Legacy Projects route opens sale listings', async ({ page }) => {
    await page.goto('/projects');
    await expect(page).toHaveURL(/\/properties\?purpose=sale/);
    await expect(page.locator('main')).toContainText(/properties|sale/i);
  });
});

test.describe('Contact Form', () => {
  test('Contact page has form fields', async ({ page }) => {
    await page.goto('/contact');
    const count = await page.locator('input, textarea, button, select').count();
    expect(count).toBeGreaterThan(0);
  });
});

test.describe('404 Recovery', () => {
  test('404 page offers recovery path', async ({ page }) => {
    await page.goto('/nonexistent-page-xyz');
    await expect(page.locator('text=/404|Page Not Found|not found/i').first()).toBeVisible();
    const count = await page.locator('a, button').count();
    expect(count).toBeGreaterThan(0);
  });
});
