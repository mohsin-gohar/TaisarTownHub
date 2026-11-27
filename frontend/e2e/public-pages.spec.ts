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

  test('Homepage filters placeholder YouTube video IDs from public tours', async ({ page }) => {
    await page.route('**/api/videos**', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([{
        id: 'placeholder-video',
        title: 'Placeholder walkthrough',
        description: 'Seed placeholder',
        videoUrl: 'https://www.youtube.com/embed/VIDEO_ID',
        thumbnailUrl: 'https://example.test/placeholder.jpg',
      }]),
    }));
    await page.goto('/');
    await expect(page.getByText('No verified video walkthroughs are available right now.')).toBeVisible();
    await expect(page.getByText('Placeholder walkthrough')).toHaveCount(0);
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

  test('mobile navigation works without horizontal page overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const menuButton = page.getByRole('button', { name: 'Open navigation menu' });
    await expect(menuButton).toBeVisible();
    await menuButton.click();

    const drawer = page.getByRole('dialog', { name: 'Navigation menu' });
    await expect(drawer).toBeVisible();
    await drawer.getByRole('button', { name: 'Properties' }).click();
    await expect(page).toHaveURL(/properties$/);
    await expect(page.getByRole('dialog', { name: 'Navigation menu' })).toHaveCount(0);

    const widths = await page.evaluate(() => ({
      viewport: window.innerWidth,
      document: document.documentElement.scrollWidth,
      body: document.body.scrollWidth,
    }));
    expect(widths.document).toBeLessThanOrEqual(widths.viewport);
    expect(widths.body).toBeLessThanOrEqual(widths.viewport);
  });

  test('desktop hero search filters have readable widths', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    const phaseWidth = (await page.getByLabel('Taiser Town phase').boundingBox())?.width ?? 0;
    const budgetWidth = (await page.getByLabel('Budget in PKR').boundingBox())?.width ?? 0;
    expect(phaseWidth).toBeGreaterThanOrEqual(220);
    expect(budgetWidth).toBeGreaterThanOrEqual(220);
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
