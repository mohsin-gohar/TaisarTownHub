import { expect, test } from '@playwright/test';

/**
 * Agency-specific WhatsApp contact CTA regression coverage.
 * API responses are stubbed so the test is deterministic and does not depend
 * on demo database content. The platform owner's number is never injected.
 */

const agency = {
  id: 'agy_whatsapp_test',
  name: 'Scheme 45 Test Agency',
  slug: 'scheme-45-test-agency',
  whatsapp: '0300-9876543',
  phone: '0300-9876543',
  city: 'Karachi',
  verification_status: 'verified',
  listing_count: 0,
  member_count: 1,
  since: '2026',
};

test('agency directory card builds a normalized WhatsApp link', async ({ page }) => {
  await page.route('**/api/agencies*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, agencies: { data: [agency], current_page: 1, last_page: 1 } }),
    });
  });
  await page.goto('/agencies');
  const link = page.locator('a[href^="https://wa.me/"]').first();
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', /wa\.me\/923009876543\?text=/);
  await expect(link).toHaveAttribute('target', '_blank');
  await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  const href = await link.getAttribute('href');
  expect(href).toContain(encodeURIComponent('Scheme 45 marketplace'));
});

test('agency profile exposes the same agency number, not a platform fallback', async ({ page }) => {
  await page.route('**/api/agencies/scheme-45-test-agency*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        agency: {
          ...agency,
          listings: { data: [], current_page: 1, last_page: 1 },
          stats: { public_listings: 0, sale_listings: 0, team_members: 1 },
        },
      }),
    });
  });
  await page.goto('/agencies/scheme-45-test-agency');
  const links = page.locator('a[href^="https://wa.me/"]');
  await expect(links.first()).toBeVisible();
  for (let i = 0; i < await links.count(); i++) {
    const href = await links.nth(i).getAttribute('href');
    expect(href).toMatch(/wa\.me\/923009876543\?text=/);
    expect(href).not.toMatch(/wa\.me\/0/);
  }
});

test('public pages never emit a broken wa.me link', async ({ page }) => {
  for (const path of ['/', '/properties', '/agencies', '/contact']) {
    await page.goto(path);
    const links = page.locator('a[href^="https://wa.me/"]');
    const count = await links.count();
    for (let i = 0; i < count; i++) {
      const href = (await links.nth(i).getAttribute('href')) || '';
      expect(href, `broken WhatsApp link on ${path}`).not.toMatch(/wa\.me\/(0|\?|$)/);
    }
  }
});
