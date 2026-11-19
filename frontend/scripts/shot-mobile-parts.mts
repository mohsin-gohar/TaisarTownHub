import { chromium } from 'playwright';

/**
 * Mobile (360px) visual probe — captures the pieces a full-page shot hides:
 * above-the-fold hero, the opened navigation drawer, and the footer, plus a
 * DOM check that the fixed navbar is actually visible (it animates in after
 * ~2s, which is why an early capture can look navbar-less).
 *
 * Usage: npx tsx scripts/shot-mobile-parts.mts [baseUrl]
 * Output: QA_OUT_DIR (default C:/Users/mohsi/AppData/Local/Temp/opencode/theme-artifacts)
 */
const BASE = process.argv[2] || process.env.QA_BASE_URL || 'http://127.0.0.1:4173';
const OUT = process.env.QA_OUT_DIR || 'C:/Users/mohsi/AppData/Local/Temp/opencode/theme-artifacts';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 360, height: 780 } });
const errors: string[] = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text().slice(0, 200)); });
page.on('pageerror', (e) => errors.push(String(e).slice(0, 200)));

await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
// Wait past the navbar entrance animation so the bar is really on screen.
await page.waitForTimeout(2500);

const nav = await page.evaluate(() => {
  const el = document.querySelector('nav');
  const burger = document.querySelector<HTMLElement>('button[aria-label*="menu" i], button[aria-label*="Menu" i]');
  const r = el?.getBoundingClientRect();
  const br = burger?.getBoundingClientRect();
  return {
    navRect: r ? { x: r.x, y: r.y, w: r.width, h: r.height } : null,
    navOpacity: el ? getComputedStyle(el).opacity : null,
    burgerRect: br ? { x: br.x, y: br.y, w: br.width, h: br.height } : null,
    burgerOpacity: burger ? getComputedStyle(burger).opacity : null,
  };
});

// 1) Above-the-fold hero
await page.screenshot({ path: `${OUT}/mobile-360-hero.png` });

// 2) Open the mobile navigation drawer
await page.locator('button[aria-label*="menu" i], button[aria-label*="Menu" i]').first().click().catch(() => {});
await page.waitForTimeout(700);
await page.screenshot({ path: `${OUT}/mobile-360-drawer.png` });
await page.keyboard.press('Escape').catch(() => {});
await page.waitForTimeout(500);

// 3) Footer
await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await page.waitForTimeout(1200);
await page.screenshot({ path: `${OUT}/mobile-360-footer.png` });

const metrics = await page.evaluate(() => ({
  scrollWidth: document.documentElement.scrollWidth,
  innerWidth: window.innerWidth,
  bodyScrollWidth: document.body.scrollWidth,
  horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
}));

console.log(JSON.stringify({ errors, nav, metrics, url: page.url() }, null, 2));
await browser.close();
