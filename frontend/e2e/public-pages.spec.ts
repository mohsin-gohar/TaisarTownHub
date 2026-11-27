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
    await expect(page.locator('nav')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();
  });

  test('Legacy Projects route redirects to sale listings', async ({ page }) => {
    await page.goto('/projects');
    await expect(page).toHaveURL(/\/properties\?purpose=sale/);
    await expect(page.locator('main')).toContainText(/properties|sale/i);
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

  test('Agency API failure does not show a false empty-filter state', async ({ page }) => {
    await page.route('**/api/agencies**', (route) => route.abort());
    await page.goto('/agencies');
    await expect(page.locator('main')).toContainText(/Could not load agencies right now/i);
    await expect(page.getByText('No agencies match your filters')).toHaveCount(0);
  });

  test('Guide page loads', async ({ page }) => {
    await page.goto('/guide');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Scheme 45|Guide/i').first()).toBeVisible();
  });

  test('Gallery page loads', async ({ page }) => {
    await page.goto('/gallery');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Video Tours|Walkthroughs/i').first()).toBeVisible();
  });

  test('Legacy video walkthrough URL opens the gallery', async ({ page }) => {
    await page.goto('/video-walkthroughs');
    await expect(page).toHaveURL(/video-walkthroughs$/);
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('text=/Video Tours|Walkthroughs/i').first()).toBeVisible();
  });

  test('Gallery explains when no verified tours are published', async ({ page }) => {
    await page.goto('/gallery');
    await expect(page.getByTestId('video-gallery-empty')).toBeVisible();
    await expect(page.getByTestId('video-gallery-empty')).toContainText(/No verified video tours/i);
  });

  test('Footer never links to generic social network homepages', async ({ page }) => {
    await page.goto('/');
    const socialLinks = page.locator('footer a[aria-label*=" on "]');
    const count = await socialLinks.count();
    for (let index = 0; index < count; index += 1) {
      const href = await socialLinks.nth(index).getAttribute('href');
      if (!href) continue;
      const hostname = new URL(href).hostname.toLowerCase();
      expect(['facebook.com', 'www.facebook.com', 'youtube.com', 'www.youtube.com', 'linkedin.com', 'www.linkedin.com', 'x.com', 'www.x.com', 'instagram.com', 'www.instagram.com']).not.toContain(hostname);
    }
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
    await page.locator('nav button:has-text("Properties")').first().click();
    await page.waitForURL('**/properties', { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();

    await page.locator('nav button:has-text("Agencies")').first().click();
    await page.waitForURL('**/agencies', { timeout: 10000 });
    await expect(page.locator('main')).toBeVisible();
  });

});
