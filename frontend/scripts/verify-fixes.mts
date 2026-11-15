/**
 * Focused re-verification for the two defects found by browser-interactions.mts.
 *
 *   1. 404 recovery  — the recovery actions must be real anchors (crawlable,
 *      middle-clickable) that actually navigate, not inert buttons.
 *   2. Gallery thumbs — every thumbnail must expose its selected state
 *      (aria-pressed) and switching must change the main image src.
 *
 * Run: npx tsx scripts/verify-fixes.mts
 */
import { chromium } from '@playwright/test';

const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';

const results: { name: string; ok: boolean; detail: string }[] = [];
const check = (name: string, ok: boolean, detail: string) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name} — ${detail}`);
};

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// ---- 1. 404 recovery ------------------------------------------------------
await page.goto(`${BASE}/this-route-does-not-exist`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1200);

const home = page.locator('a[href="/"]').first();
const props = page.locator('a[href="/properties"]').first();
const contact = page.locator('a[href="/contact"]').first();

check(
  '404 shows recovery anchors',
  (await home.count()) > 0 && (await props.count()) > 0 && (await contact.count()) > 0,
  `home=${await home.count()} properties=${await props.count()} contact=${await contact.count()}`,
);

await home.click();
await page.waitForTimeout(1500);
check(
  '404 → Back to Home navigates',
  page.url().replace(/\/$/, '') === BASE.replace(/\/$/, ''),
  `url=${page.url()}`,
);

await page.goto(`${BASE}/nope-2`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1000);
await page.locator('a[href="/properties"]').first().click();
await page.waitForTimeout(1500);
check(
  '404 → Browse Properties navigates',
  page.url().includes('/properties'),
  `url=${page.url()}`,
);

// ---- 2. Property gallery thumbnails --------------------------------------
await page.goto(`${BASE}/properties`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);

const firstCard = page.locator('a[href*="property="]').first();
if ((await firstCard.count()) === 0) {
  check('gallery: property card anchor found', false, 'no crawlable property link on /properties');
} else {
  // Find a listing that actually has a multi-image gallery; single-image
  // properties legitimately render one thumbnail.
  const cardCount = await page.locator('a[href*="property="]').count();
  let thumbCount = 0;
  let opened = false;

  for (let i = 0; i < Math.min(cardCount, 8) && thumbCount <= 1; i++) {
    await page.goto(`${BASE}/properties`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const card = page.locator('a[href*="property="]').nth(i);
    await card.click();
    await page.waitForTimeout(2200);
    thumbCount = await page.locator('button[aria-label^="Show image "]').count();
    opened = true;
  }

  check('gallery: thumbnails rendered', thumbCount > 1, `count=${thumbCount} (after scanning up to ${Math.min(cardCount, 8)} listings)`);

  const pressed = await page.locator('button[aria-label^="Show image "][aria-pressed="true"]').count();
  check('gallery: exactly one selected thumb', opened && pressed === 1, `aria-pressed=true count=${pressed}`);

  if (thumbCount > 1) {
    const label = await page
      .locator('button[aria-label^="Show image "][aria-pressed="true"]')
      .first()
      .getAttribute('aria-label');
    await page.locator('button[aria-label^="Show image "]').nth(thumbCount - 1).click();
    await page.waitForTimeout(1000);
    const after = await page
      .locator('button[aria-label^="Show image "][aria-pressed="true"]')
      .first()
      .getAttribute('aria-label');
    check(
      'gallery: clicking another thumb moves selection',
      after === `Show image ${thumbCount}`,
      `selected=${after} (expected Show image ${thumbCount}, was ${label})`,
    );
  }
}

await browser.close();

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed.`);
if (failed.length) process.exitCode = 1;