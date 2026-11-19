import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:4173';
const OUT = 'C:/Users/mohsi/AppData/Local/Temp/opencode/theme-artifacts';
await mkdir(OUT, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors: string[] = [];
page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text().slice(0, 300)); });
page.on('pageerror', (err) => errors.push(String(err).slice(0, 300)));

await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
const home = await page.evaluate(() => {
  const band = document.querySelector('section.surface-ivory');
  const nav = document.querySelector('nav, header');
  return {
    hasLightBand: Boolean(band),
    bandText: band ? (band.textContent || '').slice(0, 160) : null,
    bodyBg: getComputedStyle(document.body).backgroundColor,
    navBg: nav ? getComputedStyle(nav).backgroundColor : null,
    cards: document.querySelectorAll('section.surface-ivory article').length,
    scrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth,
  };
});
await page.screenshot({ path: `${OUT}/home-full.png`, fullPage: true });

await page.goto(`${BASE}/properties?purpose=sale`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
const props = await page.evaluate(() => {
  const main = document.querySelector('main');
  const panel = document.querySelector('main .bg-white');
  return {
    mainBg: main ? getComputedStyle(main).backgroundColor : null,
    panelBg: panel ? getComputedStyle(panel).backgroundColor : null,
    cards: document.querySelectorAll('#listing-results > div').length,
    scrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth,
  };
});
await page.screenshot({ path: `${OUT}/properties-full.png`, fullPage: true });

console.log(JSON.stringify({ home, props, errors }, null, 2));
await browser.close();
