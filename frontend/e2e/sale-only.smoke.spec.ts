import { expect, test } from '@playwright/test';

/**
 * Live smoke for the sale-only marketplace changes:
 * navbar cleanup, properties sale-only, legacy /projects redirect.
 */
test.describe('Sale-only marketplace (live)', () => {
  test('homepage renders with sale-only navigation', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('main')).toBeVisible({ timeout: 20000 });

    const nav = page.locator('nav, header').first();
    await expect(nav.getByText('Rent', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('Projects', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('More', { exact: true })).toHaveCount(0);
    await expect(nav.getByText('Properties', { exact: true })).toBeVisible();
    await expect(nav.getByText('Agencies', { exact: true })).toBeVisible();
  });

  test('properties page loads sale listings', async ({ page }) => {
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

  test('homepage search carries selected filters into sale listings', async ({ page }) => {
    await page.goto('/');
    await page.locator('select').nth(2).selectOption('Sector 70-D');
    await page.getByRole('button', { name: /^search$/i }).click();
    await expect(page).toHaveURL(/\/properties/);
    const sectorFilter = page.locator('select').filter({ has: page.locator('option[value="Sector 70-D"]') }).last();
    await expect(sectorFilter).toHaveValue('Sector 70-D');
  });

  test('legacy /projects redirects to sale properties', async ({ page }) => {
    await page.goto('/projects');
    await page.waitForTimeout(1500);
    await expect(page).toHaveURL(/purpose=sale/);
  });

  test('agencies directory still works', async ({ page }) => {
    await page.goto('/agencies');
    await expect(page.locator('main')).toBeVisible({ timeout: 20000 });
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/agency|agencies/i);
  });
});