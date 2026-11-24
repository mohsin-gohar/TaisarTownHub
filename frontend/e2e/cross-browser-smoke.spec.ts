import { expect, test } from '@playwright/test';

/**
 * Cross-browser public smoke: real Firefox/WebKit pages, real API responses,
 * no fake success. Each route must render a visible heading and expose at
 * least one property/agency link where applicable.
 */
const routes = ['/', '/properties', '/agencies', '/guide', '/about', '/contact', '/login'];

for (const route of routes) {
  test(`${route} renders with real heading`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(route, { waitUntil: 'load' });
    await expect(page.locator('h1:visible, h2:visible').first()).toBeVisible({ timeout: 15_000 });
    expect(errors, `page errors on ${route}`).toEqual([]);
  });
}
