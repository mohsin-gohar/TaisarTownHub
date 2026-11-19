import { expect, test } from '@playwright/test';

const API_BASE = process.env.PLAYWRIGHT_API_URL || process.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

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

  test('properties page renders exactly the governed public sale listings', async ({ page, request }) => {
    // The live API defines the public set: agency-owned + approved + public + sale.
    const response = await request.get(`${API_BASE}/properties?purpose=sale&per_page=500`);
    expect(response.ok()).toBeTruthy();
    const rows = await response.json();
    expect(Array.isArray(rows), 'the public properties contract must stay a raw list').toBe(true);

    await page.goto('/properties?purpose=sale');
    await expect(page.locator('main')).toBeVisible({ timeout: 20000 });

    // The UI must not silently show a different dataset (legacy catalog rows,
    // test artefacts) or an empty state when the API has listings.
    await expect(page.getByText(`Found ${rows.length} matching properties`)).toBeVisible({ timeout: 20000 });

    for (const artifact of ['WORKFLOW-', 'PROBE-ONLY-', 'Load-test property']) {
      await expect(page.locator('body')).not.toContainText(artifact);
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