import { expect, test } from '@playwright/test';

/**
 * Responsive regression guard for the approved premium theme: every public
 * route must keep its shell visible and avoid page-level horizontal overflow
 * at mobile (360px), tablet and desktop widths. No visual redesign is applied.
 */

const viewports = [
  { name: 'mobile 360px', width: 360, height: 780 },
  { name: 'tablet 768px', width: 768, height: 1024 },
  { name: 'desktop 1280px', width: 1280, height: 900 },
];

const publicRoutes = [
  '/', '/properties', '/agencies', '/guide', '/gallery',
  '/services', '/about', '/contact', '/login', '/register', '/admin-login',
];

for (const viewport of viewports) {
  test(`${viewport.name}: all public pages render without horizontal overflow`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });

    for (const route of publicRoutes) {
      await page.goto(route);
      // Auth routes render their panel outside the (empty) <main> element, so
      // assert a visible heading rather than a specific shell container.
      await expect(page.locator('h1:visible, h2:visible').first(), `${route} heading missing`).toBeVisible({ timeout: 10_000 });

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `${route} overflows by ${overflow}px at ${viewport.name}`).toBeLessThanOrEqual(2);
    }
  });
}
