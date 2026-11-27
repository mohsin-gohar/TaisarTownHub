import { chromium } from '@playwright/test';

const ctx = await chromium.launchPersistentContext(
  'C:\\Users\\mohsi\\AppData\\Local\\Temp\\kilo\\chrome-if-profile',
  {
    headless: false,
    viewport: null,
    args: [
      '--remote-debugging-port=9222',
      '--no-first-run',
      '--no-default-browser-check',
      '--start-maximized',
    ],
  }
);

const pages = ctx.pages();
const page = pages[0] || (await ctx.newPage());
await page.goto('https://app.infinityfree.com/', { waitUntil: 'domcontentloaded' }).catch(() => {});
console.log('OPENED', page.url());
console.log('TITLE', await page.title().catch(() => ''));

// Keep the browser (and CDP port) alive for the whole session.
await new Promise(() => {});
