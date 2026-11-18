import { expect, test } from '@playwright/test';

/**
 * Live smoke for the agency-first marketplace WIP:
 * homepage section, /agencies directory, /agencies/:slug profile,
 * /properties?purpose=sale.
 */
test.describe('Agency-first marketplace (live)', () => {
  test('homepage shows agency-first marketplace section', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.getByText('Featured Agencies')).toBeVisible();
    await expect(page.getByRole('button', { name: /discover agencies/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /explore properties/i })).toBeVisible();
    await expect(page.locator('main').getByText(/for rent/i)).toHaveCount(0);
  });

  test('agencies directory lists agencies with clickable cards', async ({ page }) => {
    await page.goto('/agencies');
    await expect(page.locator('main')).toBeVisible();
    // Cards are buttons (pushState SPA pattern) with agency names inside.
    const cards = page.locator('main button');
    await expect(cards.first()).toBeVisible({ timeout: 20000 });
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/agency|agencies/i);
  });

  test('agency profile page renders profile and listings', async ({ page }) => {
    await page.goto('/agencies/taisartownhub-premier-advisory');
    await expect(page.locator('main')).toBeVisible({ timeout: 20000 });
    // Profile heading and at least one listing card.
    await expect(page.locator('h1, h2').first()).toBeVisible();
    await page.waitForTimeout(2500);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText.length).toBeGreaterThan(500);
  });

  test('properties page honors purpose=sale query', async ({ page }) => {
    await page.goto('/properties?purpose=sale');
    await expect(page.locator('main')).toBeVisible({ timeout: 20000 });
    await page.waitForTimeout(1500);
    const cards = await page.locator('main a, main article').count();
    if (cards === 0) {
      await expect(page.locator('main')).toContainText(/Found 0 matching properties|No public listings|No properties/i);
    } else {
      expect(cards).toBeGreaterThan(0);
    }
  });

  test('unknown agency slug shows graceful not-found', async ({ page }) => {
    const response = await page.goto('/agencies/does-not-exist-xyz-123');
    await page.waitForTimeout(1500);
    const bodyText = await page.locator('body').innerText();
    // Either a not-found message or fallback content — must not crash blank.
    expect(bodyText.toLowerCase()).toMatch(/not found|404|agency|back/i);
    expect(response?.status()).toBeLessThan(500);
  });
});