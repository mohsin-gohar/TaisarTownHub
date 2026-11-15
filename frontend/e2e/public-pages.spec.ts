import { expect, test } from '@playwright/test';

/**
 * PHASE 3 — Browser-based test of every PUBLIC page.
 * Uses robust selectors based on actual DOM structure.
 */

test.describe('Public Pages', () => {

  test('Homepage renders all sections', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Taiser Town|TaisarTownHub/i);
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('h1, h2').first()).toBeVisible();
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();
  });

  test('Projects page loads', async ({ page }) => {
    await page.goto('/projects');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Projects/i').first()).toBeVisible();
  });

  test('Properties page loads', async ({ page }) => {
    await page.goto('/properties');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Properties/i').first()).toBeVisible();
  });

  test('Agencies page loads', async ({ page }) => {
    await page.goto('/agencies');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Agency/i').first()).toBeVisible();
  });

  test('Guide page loads', async ({ page }) => {
    await page.goto('/guide');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Scheme 45|Guide/i').first()).toBeVisible();
  });

  test('Gallery page loads', async ({ page }) => {
    await page.goto('/gallery');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Gallery/i').first()).toBeVisible();
  });

  test('Services page loads', async ({ page }) => {
    await page.goto('/services');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Services/i').first()).toBeVisible();
  });

  test('About page loads', async ({ page }) => {
    await page.goto('/about');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/About/i').first()).toBeVisible();
  });

  test('Contact page loads', async ({ page }) => {
    await page.goto('/contact');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Contact/i').first()).toBeVisible();
  });

  test('Login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('input[placeholder*="admin@taisartownhub.com" i], input[placeholder*="dealer email" i]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
  });

  test('Register page renders', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('main')).toBeVisible();
  });

  test('404 page renders for unknown routes', async ({ page }) => {
    await page.goto('/nonexistent-page-xyz-12345');
    await expect(page.locator('text=/404|Page Not Found|not found/i').first()).toBeVisible();
  });

  test('Navbar links navigate between pages', async ({ page }) => {
    await page.goto('/');
    await page.locator('nav a:has-text("Projects"), nav [role="menuitem"]:has-text("Projects")').first().click();
    await page.waitForURL('**/projects', { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();

    await page.locator('nav a:has-text("Properties"), nav [role="menuitem"]:has-text("Properties")').first().click();
    await page.waitForURL('**/properties', { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();

    await page.locator('nav a:has-text("Agencies"), nav [role="menuitem"]:has-text("Agencies")').first().click();
    await page.waitForURL('**/agencies', { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();
  });

});
