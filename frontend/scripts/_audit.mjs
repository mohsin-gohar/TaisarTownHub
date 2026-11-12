import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';
const OUT = path.resolve('.screenshots/audit');
fs.mkdirSync(OUT, { recursive: true });

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const publicRoutes = [
  ['home', '/'],
  ['projects', '/projects'],
  ['properties', '/properties'],
  ['agencies', '/agencies'],
  ['guide', '/guide'],
  ['gallery', '/gallery'],
  ['services', '/services'],
  ['about', '/about'],
  ['contact', '/contact'],
  ['login', '/login'],
  ['register', '/register'],
  ['property-detail', '/properties?property=prop-r66'],
  ['project-detail', '/projects?project=proj-1'],
];

const adminTabs = [
  'overview', 'properties', 'plot_locator', 'transactions', 'sellers', 'buyers',
  'dealers', 'file_management', 'projects', 'media', 'inquiries', 'cms',
  'security', 'audit', 'agency_dashboard', 'staff', 'documents', 'moderation',
  'crm', 'media_studio', 'subscription', 'locations', 'notifications',
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function settle(page) {
  try { await page.waitForNetworkIdle({ idleTime: 700, timeout: 12000 }); }
  catch { /* fall through */ }
  await sleep(600);
}

function attachDiagnostics(page, bucket) {
  page.on('console', (msg) => {
    if (msg.type() === 'error') bucket.console.push(msg.text().slice(0, 300));
  });
  page.on('pageerror', (err) => bucket.pageerror.push(String(err).slice(0, 300)));
  page.on('requestfailed', (req) => {
    const u = req.url();
    if (u.includes('favicon')) return;
    bucket.reqfail.push(`${req.method()} ${u} :: ${req.failure()?.errorText}`);
  });
  page.on('response', (res) => {
    const s = res.status();
    if (s >= 400 && !res.url().includes('favicon')) bucket.http.push(`${s} ${res.url()}`);
  });
}

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--window-size=1440,900'],
    defaultViewport: { width: 1440, height: 900 },
  });

  const report = { public: {}, admin: {}, login: {} };

  const page = await browser.newPage();
  const diag = { console: [], pageerror: [], reqfail: [], http: [] };
  attachDiagnostics(page, diag);

  for (const [name, route] of publicRoutes) {
    diag.console = []; diag.pageerror = []; diag.reqfail = []; diag.http = [];
    try {
      await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await settle(page);
      const file = path.join(OUT, `public-${name}.png`);
      await page.screenshot({ path: file, fullPage: name === 'home' });
      const title = await page.title();
      const h1 = await page.$eval('h1', (el) => el.textContent?.trim()).catch(() => null);
      report.public[name] = { route, title, h1, ...diag };
    } catch (e) {
      report.public[name] = { route, fatal: String(e).slice(0, 300), ...diag };
    }
  }

  // --- Login as seeded agency admin ---
  try {
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('input[type="email"]', { timeout: 15000 });
    await page.type('input[type="email"]', 'admin@taisartownhub.com');
    await page.type('input[type="password"]', 'Admin@Taisar2026');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {}),
      page.click('button[type="submit"]'),
    ]);
    await settle(page);
    await page.waitForFunction(() => location.pathname.startsWith('/admin'), { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    report.login = {
      url: page.url(),
      diag: { console: [...diag.console], pageerror: [...diag.pageerror] },
    };
    diag.console = []; diag.pageerror = []; diag.reqfail = []; diag.http = [];
  } catch (e) {
    report.login = { fatal: String(e).slice(0, 300) };
  }

  // --- Admin tabs ---
  for (const tab of adminTabs) {
    diag.console = []; diag.pageerror = []; diag.reqfail = []; diag.http = [];
    try {
      await page.goto(`${BASE}/admin/${tab}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await settle(page);
      await sleep(900);
      const file = path.join(OUT, `admin-${tab}.png`);
      await page.screenshot({ path: file });
      const bodyText = await page.evaluate(() => document.body.innerText.slice(0, 400));
      report.admin[tab] = { ...diag, bodyText };
    } catch (e) {
      report.admin[tab] = { fatal: String(e).slice(0, 300), ...diag };
    }
  }

  await browser.close();
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2));
  console.log('WROTE', path.join(OUT, 'report.json'));

  // Compact console summary
  const problems = [];
  for (const [group, entries] of Object.entries(report)) {
    if (group === 'login') continue;
    for (const [name, r] of Object.entries(entries)) {
      const errs = [...(r.console || []), ...(r.pageerror || [])];
      const bad = (r.http || []).filter((x) => !x.includes('/api/'));
      if (errs.length || bad.length || r.fatal) {
        problems.push({ group, name, fatal: r.fatal, errs, bad });
      }
    }
  }
  console.log('PROBLEM COUNT', problems.length);
  console.log(JSON.stringify(problems.slice(0, 60), null, 1));
})().catch((e) => { console.error('FATAL', e); process.exit(1); });