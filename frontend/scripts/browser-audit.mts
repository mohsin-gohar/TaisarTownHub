/**
 * Browser audit crawler (one-off, not part of CI gates).
 *
 * Visits every public route (desktop + mobile), logs in as the seeded
 * superadmin and walks every visible admin tab. For each stop it records:
 *   - console errors / page errors / failed requests / HTTP >= 400
 *   - dead anchors (href missing, "#", javascript:)
 *   - broken images (naturalWidth === 0)
 *   - empty content (root text length)
 *   - a full-page screenshot
 *
 * Output: frontend/audit/report.json + frontend/audit/*.png
 */
import { chromium, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';
const OUT = path.resolve(import.meta.dirname, '..', 'audit');
mkdirSync(OUT, { recursive: true });

type Finding = {
  page: string;
  consoleErrors: string[];
  pageErrors: string[];
  failedRequests: string[];
  httpErrors: string[];
  deadAnchors: string[];
  brokenImages: string[];
  textLength: number;
  screenshot: string;
};

const IGNORE_CONSOLE = [
  /Download the React DevTools/i,
  /React Router Future Flag/i,
  /preloaded using link preload but not used/i,
];

function slug(input: string): string {
  return input.replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').slice(0, 80) || 'root';
}

async function collect(page: Page, label: string): Promise<Finding> {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const failedRequests: string[] = [];
  const httpErrors: string[] = [];

  // Scroll to load lazy images/sections, then return to top.
  await page.evaluate(async () => {
    const step = window.innerHeight;
    for (let y = 0; y < document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 120));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(800);

  const checks = await page.evaluate(() => {
    const deadAnchors: string[] = [];
    document.querySelectorAll('a').forEach((a) => {
      const href = a.getAttribute('href');
      const text = (a.textContent || '').trim().slice(0, 60);
      if (!href || href === '#' || href.startsWith('javascript:')) {
        deadAnchors.push(`${text || '(no text)'} → href=${href === null ? 'MISSING' : JSON.stringify(href)}`);
      }
    });
    const brokenImages: string[] = [];
    document.querySelectorAll('img').forEach((img) => {
      if (img.complete && img.naturalWidth === 0) {
        brokenImages.push((img.getAttribute('src') || '(no src)').slice(0, 140));
      }
    });
    const rootText = (document.getElementById('root')?.textContent || '').trim();
    return { deadAnchors, brokenImages, textLength: rootText.length };
  });

  const file = `${slug(label)}.png`;
  await page.screenshot({ path: path.join(OUT, file), fullPage: true });

  return {
    page: label,
    consoleErrors,
    pageErrors,
    failedRequests,
    httpErrors,
    ...checks,
    screenshot: file,
  };
}

async function newPage(browser: Browser, viewport: { width: number; height: number }): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  return { context, page };
}

async function auditRoute(
  browser: Browser,
  route: string,
  viewport: { width: number; height: number },
  suffix = '',
): Promise<Finding> {
  const { context, page } = await newPage(browser, viewport);

  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const failedRequests: string[] = [];
  const httpErrors: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error' && !IGNORE_CONSOLE.some((r) => r.test(msg.text()))) {
      consoleErrors.push(msg.text().slice(0, 300));
    }
  });
  page.on('pageerror', (err) => pageErrors.push(String(err).slice(0, 300)));
  page.on('requestfailed', (req) => {
    failedRequests.push(`${req.method()} ${req.url().slice(0, 160)} — ${req.failure()?.errorText || 'failed'}`);
  });
  page.on('response', (res) => {
    if (res.status() >= 400 && !res.url().includes('/api/')) {
      httpErrors.push(`${res.status()} ${res.url().slice(0, 160)}`);
    }
  });

  await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.waitForTimeout(2200);

  const label = `${route}${suffix}`;
  const finding = await collect(page, label);
  finding.consoleErrors = consoleErrors;
  finding.pageErrors = pageErrors;
  finding.failedRequests = failedRequests;
  finding.httpErrors = httpErrors;

  await context.close();
  return finding;
}

async function auditAdmin(browser: Browser): Promise<Finding[]> {
  const findings: Finding[] = [];
  const { context, page } = await newPage(browser, { width: 1440, height: 900 });

  let current = 'login';
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const failedRequests: string[] = [];
  const httpErrors: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error' && !IGNORE_CONSOLE.some((r) => r.test(msg.text()))) {
      consoleErrors.push(msg.text().slice(0, 300));
    }
  });
  page.on('pageerror', (err) => pageErrors.push(String(err).slice(0, 300)));
  page.on('requestfailed', (req) => {
    failedRequests.push(`${req.method()} ${req.url().slice(0, 160)} — ${req.failure()?.errorText || 'failed'}`);
  });
  page.on('response', (res) => {
    if (res.status() >= 400 && !res.url().includes('/api/')) {
      httpErrors.push(`${res.status()} ${res.url().slice(0, 160)}`);
    }
  });

  // ---- login as seeded superadmin ----
  await page.goto(BASE + '/admin-login', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  // /admin-login renders the public site + a login MODAL; the footer also has
  // a submit button, so scope every interaction to the modal's <form>.
  const loginForm = page
    .locator('form')
    .filter({ has: page.locator('input[placeholder="Enter password"]') })
    .first();
  const loginEmail = process.env.AUDIT_EMAIL || 'superadmin@taisartownhub.com';
  const loginPassword = process.env.AUDIT_PASSWORD || 'Super@Admin2026!';
  await loginForm.locator('input[type="email"]').fill(loginEmail);
  await loginForm.locator('input[type="password"]').fill(loginPassword);
  await loginForm.locator('button[type="submit"]').click();
  await page.waitForTimeout(5000);

  const loginShot = await collect(page, current);
  loginShot.consoleErrors.push(...consoleErrors.splice(0));
  loginShot.pageErrors.push(...pageErrors.splice(0));
  loginShot.failedRequests.push(...failedRequests.splice(0));
  loginShot.httpErrors.push(...httpErrors.splice(0));
  findings.push(loginShot);

  // ---- walk every visible sidebar tab ----
  const tabLabels = [
    'Dashboard Overview',
    'Agency approvals',
    'Agency Dashboard',
    'Property Listings',
    'Plot Map & Locator',
    'Deals & Transactions',
    'Sellers & Owners',
    'Buyers CRM',
    'Dealers & Commission',
    'File Verification',
    'Projects Management',
    'Media Library',
    'Inquiries CRM',
    'Website CMS & Builder',
    'Staff & Roles',
    'Document Vault',
    'CRM Pipeline',
    'Media Studio',
    'Subscription & Usage',
    'Moderation Desk',
    'Locations & Categories',
    'Notifications',
    'Security & Roles',
    'Audit & Recycle Bin',
  ];

  for (const label of tabLabels) {
    const item = page.locator(`button:has-text("${label}")`).first();
    if (!(await item.isVisible().catch(() => false))) {
      findings.push({
        page: `admin / ${label}`,
        consoleErrors: [], pageErrors: [], failedRequests: [], httpErrors: [],
        deadAnchors: [], brokenImages: [], textLength: -1,
        screenshot: '(tab not visible for this role)',
      });
      continue;
    }

    current = `admin-${slug(label)}`;
    await item.click();
    await page.waitForTimeout(1800);

    const finding = await collect(page, `admin / ${label}`);
    finding.consoleErrors = consoleErrors.splice(0);
    finding.pageErrors = pageErrors.splice(0);
    finding.failedRequests = failedRequests.splice(0);
    finding.httpErrors = httpErrors.splice(0);
    findings.push(finding);
  }

  await context.close();
  return findings;
}

(async () => {
  const browser = await chromium.launch();
  const findings: Finding[] = [];
  const started = Date.now();

  const publicRoutes = [
    '/',
    '/projects',
    '/properties',
    '/agencies',
    '/guide',
    '/gallery',
    '/services',
    '/about',
    '/contact',
    '/login',
    '/register',
    '/reset-password?token=x&email=a@b.test',
    '/verify-email?email=a@b.test&token=x',
  ];

  for (const route of publicRoutes) {
    findings.push(await auditRoute(browser, route, { width: 1440, height: 900 }));
  }

  // Mobile pass (375×812) on the main public routes.
  for (const route of ['/', '/properties', '/login']) {
    findings.push(await auditRoute(browser, route, { width: 375, height: 812 }, ' [mobile]'));
  }

  findings.push(...(await auditAdmin(browser)));

  await browser.close();

  const report = { base: BASE, generatedAt: new Date().toISOString(), durationMs: Date.now() - started, findings };
  writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2));

  // ---- console summary ----
  console.log(`\nAudit complete in ${Math.round((Date.now() - started) / 1000)}s → ${OUT}`);
  let issues = 0;
  for (const f of findings) {
    const lines: string[] = [];
    if (f.pageErrors.length) lines.push(`  PAGE ERRORS: ${f.pageErrors.join(' | ')}`);
    if (f.consoleErrors.length) lines.push(`  CONSOLE: ${f.consoleErrors.slice(0, 4).join(' | ')}`);
    if (f.httpErrors.length) lines.push(`  HTTP: ${f.httpErrors.slice(0, 4).join(' | ')}`);
    if (f.failedRequests.length) lines.push(`  NET: ${f.failedRequests.slice(0, 4).join(' | ')}`);
    if (f.deadAnchors.length) lines.push(`  DEAD ANCHORS (${f.deadAnchors.length}): ${f.deadAnchors.slice(0, 5).join(' | ')}`);
    if (f.brokenImages.length) lines.push(`  BROKEN IMAGES (${f.brokenImages.length}): ${f.brokenImages.slice(0, 5).join(' | ')}`);
    if (f.textLength === 0) lines.push('  EMPTY CONTENT');
    if (f.textLength === -1) lines.push('  (tab not visible — skip)');
    if (lines.length) {
      issues++;
      console.log(`\n[${f.page}]`);
      lines.forEach((l) => console.log(l));
    }
  }
  if (issues === 0) console.log('No issues recorded.');
})();
