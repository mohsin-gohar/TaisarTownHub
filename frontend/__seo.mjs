// TEMPORARY SEO verification (deleted after use).
// Part A: validates the prerendered HTML files baked by scripts/prerender.mts.
// Part B: validates the runtime <head> sync during client-side navigation.
import puppeteer from 'puppeteer-core';

const BASE = process.env.SMOKE_BASE || 'http://localhost:4173';
const EXEC = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const grab = (html, re) => (html.match(re)?.[1] ?? '').trim();

let fail = 0;
const check = (ok, label, detail = '') => {
  if (ok) console.log(`  OK   ${label}`);
  else {
    fail++;
    console.log(`  FAIL ${label} ${detail}`);
  }
};

// ---------------------------------------------------------------- Part A
console.log('=== PART A: prerendered static HTML ===');
const ROUTES = ['', 'properties', 'projects', 'agencies', 'guide', 'gallery', 'services', 'about', 'contact', 'login', 'admin'];
const seenTitles = new Map();

for (const route of ROUTES) {
  const file = route === '' ? 'index.html' : `${route}.html`;
  const res = await fetch(`${BASE}/${file}`);
  const html = await res.text();
  const title = grab(html, /<title>([\s\S]*?)<\/title>/);
  const canonical = grab(html, /<link rel="canonical" href="([^"]+)"/);
  const robots = grab(html, /<meta name="robots" content="([^"]+)"/);
  const ogImage = grab(html, /<meta property="og:image" content="([^"]+)"/);

  const expectNoindex = route === 'login' || route === 'admin';
  check(res.ok, `${file} served (${res.status})`);
  check(title.length > 10, `${file} has a title`, `-> "${title}"`);
  check(canonical.startsWith('https://taisartownhub.vercel.app/'), `${file} canonical`, `-> ${canonical}`);
  check(robots.includes(expectNoindex ? 'noindex' : 'index'), `${file} robots=${robots}`);
  check(ogImage.endsWith('/og-image.jpg'), `${file} og:image`, `-> ${ogImage}`);

  if (title) {
    const prev = seenTitles.get(title);
    check(!prev || prev === route, `${file} title unique`, prev ? `duplicate of "${prev}"` : '');
    seenTitles.set(title, route);
  }
}

console.log(`  (${seenTitles.size} unique titles across ${ROUTES.length} routes)`);

// ---------------------------------------------------------------- Part B
console.log('\n=== PART B: runtime head sync (client-side routing) ===');
const browser = await puppeteer.launch({
  executablePath: EXEC,
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--window-size=1440,900'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });

const head = () =>
  page.evaluate(() => ({
    title: document.title,
    desc: document.querySelector('meta[name="description"]')?.getAttribute('content') || '',
    canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href') || '',
    robots: document.querySelector('meta[name="robots"]')?.getAttribute('content') || '',
    ogTitle: document.querySelector('meta[property="og:title"]')?.getAttribute('content') || '',
    ogUrl: document.querySelector('meta[property="og:url"]')?.getAttribute('content') || '',
    breadcrumb: document.getElementById('route-breadcrumb-jsonld')?.textContent || '',
  }));

await page.goto(`${BASE}/`, { waitUntil: 'load' });
await new Promise((r) => setTimeout(r, 900));
let h = await head();
check(h.title.includes('Taiser Town Scheme 45 Plots'), 'home title', `-> ${h.title}`);
check(h.canonical === 'https://taisartownhub.vercel.app/', 'home canonical', `-> ${h.canonical}`);

// Click the semantic "About" nav anchor
const clicked = await page.evaluate(() => {
  const a = [...document.querySelectorAll('header a[href], nav a[href], a[href]')].find(
    (el) => el.getAttribute('href') === '/about',
  );
  if (!a) return false;
  a.click();
  return true;
});
check(clicked, 'about nav anchor present + clicked');
await new Promise((r) => setTimeout(r, 1100));
h = await head();
check(h.title.startsWith('About TaisarTownHub'), 'about title after SPA nav', `-> ${h.title}`);
check(h.canonical === 'https://taisartownhub.vercel.app/about', 'about canonical', `-> ${h.canonical}`);
check(h.ogUrl === h.canonical, 'about og:url matches canonical');
check(h.breadcrumb.includes('BreadcrumbList'), 'about breadcrumb JSON-LD injected');
check(h.breadcrumb.includes('"name":"About Us"'), 'about breadcrumb names About Us');

// Property detail deep link -> unique title from the record
await page.goto(`${BASE}/properties?property=prop-1`, { waitUntil: 'load' });
await new Promise((r) => setTimeout(r, 1400));
h = await head();
check(!h.title.startsWith('Plots for Sale in'), 'property detail title is record-specific', `-> ${h.title}`);
check(h.canonical === 'https://taisartownhub.vercel.app/properties?property=prop-1', 'property detail canonical keeps id', `-> ${h.canonical}`);
check(h.desc.length > 40, 'property detail description present');
console.log(`       title: ${h.title}`);
console.log(`       desc : ${h.desc.slice(0, 120)}...`);

// Back-office must be noindex
await page.goto(`${BASE}/admin`, { waitUntil: 'load' });
await new Promise((r) => setTimeout(r, 1200));
h = await head();
check(h.robots.includes('noindex'), '/admin robots noindex', `-> ${h.robots}`);

await browser.close();
console.log(`\nRESULT: ${fail === 0 ? 'ALL SEO CHECKS PASSED' : `${fail} CHECK(S) FAILED`}`);
process.exit(0);
