import { chromium } from 'playwright';

const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:4173';
const OUT = process.env.QA_OUT_DIR || 'C:/Users/mohsi/AppData/Local/Temp/opencode/theme-artifacts';
const name = process.argv[2] || 'current-home';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors: string[] = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text().slice(0, 200)); });
page.on('pageerror', (e) => errors.push(String(e).slice(0, 200)));

await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
await page.waitForTimeout(1500);

// Scroll through the page first so IntersectionObserver reveals (Reveal.tsx)
// fire before the full-page capture; then return to the top.
await page.evaluate(async () => {
  const step = Math.max(300, Math.floor(window.innerHeight * 0.7));
  for (let y = 0; y <= document.body.scrollHeight; y += step) {
    window.scrollTo(0, y);
    await new Promise((r) => setTimeout(r, 120));
  }
  window.scrollTo(0, document.body.scrollHeight);
});
await page.waitForTimeout(900);
await page.evaluate(() => window.scrollTo(0, 0));
await page.waitForTimeout(900);

await page.screenshot({ path: `${OUT}/${name}-full.png`, fullPage: true });

console.log(JSON.stringify({
  name,
  url: page.url(),
  title: await page.title(),
  errors,
  scrollWidth: await page.evaluate(() => document.documentElement.scrollWidth),
  innerWidth: await page.evaluate(() => window.innerWidth),
  sections: await page.evaluate(() =>
    Array.from(document.querySelectorAll('section')).map((s, i) => ({
      i,
      h: (s.querySelector('h1,h2,h3')?.textContent || '').trim().slice(0, 70),
      bg: getComputedStyle(s).backgroundColor,
    })),
  ),
}, null, 2));

await browser.close();
