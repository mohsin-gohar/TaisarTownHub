/**
 * Admin interactive audit (one-off, not part of CI gates).
 *
 * Logs into the admin via the /admin-login modal and walks every visible
 * sidebar tab, clicking every content button once (skipping destructive
 * actions and form submits) to detect dead clicks, broken dialogs and
 * runtime errors. Repeats for manager@taisartownhub.com and
 * dealer@taisartownhub.com so the agency enterprise tabs get exercised too.
 *
 * Output: frontend/audit/admin-interactions.json
 */
import { chromium, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';
const OUT = path.resolve(import.meta.dirname, '..', 'audit');
mkdirSync(OUT, { recursive: true });

const IGNORE_CONSOLE = [
  /Download the React DevTools/i,
  /React Router Future Flag/i,
  /preloaded using link preload but not used/i,
];

const DESTRUCTIVE = /delete|remove|sign out|log out|logout|deactivate|reject|decline|approve|mark (as )?(paid|resolved|closed)|pay now|refund|suspend|terminate|revoke|purge|archive|publish|unpublish|reset password|clear all|wipe/i;

const TAB_LABELS = [
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

type Sink = {
  consoleErrors: string[];
  pageErrors: string[];
  failedRequests: string[];
  httpErrors: string[];
};

type ProbeResult = {
  page: string;
  button: string;
  effect: string;
  urlBefore: string;
  urlAfter: string;
  errors: string[];
};

function normalizeRoute(pathname: string): string {
  return pathname.replace(/\/+$/, '') || '/';
}

function normalizeName(raw: string): string {
  return raw.replace(/\s+/g, ' ').trim().slice(0, 90);
}

async function gotoAdmin(page: Page) {
  await page.goto(BASE + '/admin-login', { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.waitForTimeout(1500);
}

async function login(page: Page, email: string, password: string) {
  const loginForm = page
    .locator('form')
    .filter({ has: page.locator('input[placeholder="Enter password"]') })
    .first();
  await loginForm.locator('input[type="email"]').fill(email);
  await loginForm.locator('input[type="password"]').fill(password);
  await loginForm.locator('button[type="submit"]').click();
  await page.waitForTimeout(4500);
}

async function logout(page: Page) {
  const signOut = page.getByRole('button', { name: /Sign Out/i }).first();
  if (await signOut.isVisible().catch(() => false)) {
    await signOut.click();
    await page.waitForTimeout(1200);
  }
}

function attachSink(page: Page): Sink {
  const sink: Sink = { consoleErrors: [], pageErrors: [], failedRequests: [], httpErrors: [] };
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !IGNORE_CONSOLE.some((r) => r.test(msg.text()))) {
      sink.consoleErrors.push(msg.text().slice(0, 300));
    }
  });
  page.on('pageerror', (err) => sink.pageErrors.push(String(err).slice(0, 300)));
  page.on('requestfailed', (req) => {
    sink.failedRequests.push(`${req.method()} ${req.url().slice(0, 160)} — ${req.failure()?.errorText || 'failed'}`);
  });
  page.on('response', (res) => {
    if (res.status() >= 400) {
      sink.httpErrors.push(`${res.status()} ${res.request().method()} ${res.url().slice(0, 160)}`);
    }
  });
  return sink;
}

function takeErrors(sink: Sink): string[] {
  const out = [
    ...sink.pageErrors.splice(0),
    ...sink.consoleErrors.splice(0),
    ...sink.failedRequests.splice(0),
    ...sink.httpErrors.splice(0),
  ];
  return out;
}

async function snapshot(page: Page) {
  return page.evaluate(() => {
    const root = document.getElementById('root');
    const html = root?.innerHTML || '';
    let hash = 0;
    for (let i = 0; i < html.length; i += 1) hash = ((hash << 5) - hash + html.charCodeAt(i)) | 0;
    const overlays = document.querySelectorAll('[role="dialog"], [aria-modal="true"], .fixed.inset-0').length;
    return {
      url: location.href,
      hash: String(hash),
      overlays,
      scrollY: Math.round(window.scrollY),
      textLength: (root?.innerText || '').trim().length,
    };
  });
}

async function dismissOverlay(page: Page) {
  // Escape first, then fall back to clicking the backdrop/cancel.
  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(450);
  const overlayCount = await page.locator('[role="dialog"], [aria-modal="true"], .fixed.inset-0').count();
  if (overlayCount > 0) {
    const cancel = page.locator('button').filter({ hasText: /cancel/i }).first();
    if (await cancel.isVisible().catch(() => false)) {
      await cancel.click().catch(() => {});
      await page.waitForTimeout(400);
    }
  }
}

async function contentButtons(page: Page) {
  return page.locator('main').locator('button').evaluateAll(
    (els) =>
      els.map((el, index) => {
        const rect = el.getBoundingClientRect();
        const style = getComputedStyle(el);
        const rawText = (el as HTMLElement).innerText || '';
        const name = (el.getAttribute('aria-label') || rawText || (el.getAttribute('title') as string) || '')
          .replace(/\s+/g, ' ')
          .trim()
          .slice(0, 90);
        return {
          index,
          name,
          type: el.getAttribute('type') || 'submit',
          inForm: Boolean(el.closest('form')),
          disabled: (el as HTMLButtonElement).disabled || el.getAttribute('aria-disabled') === 'true',
          visible:
            rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none' && style.opacity !== '0',
        };
      }),
  );
}

async function mainTabProbe(page: Page, tabLabel: string): Promise<ProbeResult[]> {
  const results: ProbeResult[] = [];
  if (!page.url().includes('/admin')) {
    await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await page.waitForTimeout(1500);
  }
  const tabButton = page.getByRole('button', { name: new RegExp(`^${tabLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }).first();
  if (!(await tabButton.isVisible().catch(() => false))) return results;
  await tabButton.click();
  await page.waitForTimeout(1600);

  const sink = attachSink(page);
  const before = await snapshot(page);
  const buttons = (await contentButtons(page)).filter((b) => b.visible && !b.disabled);
  const seen = new Set<string>();
  const targets = buttons.filter((b) => {
    if (b.type === 'submit' && b.inForm) return false;
    if (!b.name) return false;
    if (DESTRUCTIVE.test(b.name)) return false;
    const key = b.name.toLowerCase();
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });

  for (const target of targets) {
    const sink2 = attachSink(page);
    const probeBefore = await snapshot(page);
    let popup: Page | null = null;
    const popupPromise = page.context().waitForEvent('page', { timeout: 700 }).catch(() => null);
    const buttonLocator = page.getByRole('button', { name: target.name, exact: true }).first();
    if (!(await buttonLocator.isVisible().catch(() => false))) {
      results.push({ page: `admin / ${tabLabel}`, button: target.name, effect: 'NOT FOUND / NOT VISIBLE', urlBefore: probeBefore.url, urlAfter: probeBefore.url, errors: takeErrors(sink2) });
      continue;
    }
    await buttonLocator.scrollIntoViewIfNeeded().catch(() => {});
    try {
      await buttonLocator.click({ timeout: 6000 });
    } catch (error) {
      results.push({ page: `admin / ${tabLabel}`, button: target.name, effect: `CLICK FAILED: ${String(error).slice(0, 160)}`, urlBefore: probeBefore.url, urlAfter: probeBefore.url, errors: takeErrors(sink2) });
      continue;
    }
    popup = await popupPromise;
    if (popup) await popup.close().catch(() => {});
    await page.waitForTimeout(900);
    const after = await snapshot(page);
    await dismissOverlay(page);

    let effect = '';
    if (popup) effect = 'opened popup';
    else if (normalizeRoute(new URL(after.url).pathname) !== normalizeRoute(new URL(before.url).pathname)) effect = 'route changed';
    else if (after.overlays > before.overlays) effect = 'opened overlay/dialog';
    else if (after.overlays > probeBefore.overlays) effect = 'opened overlay/dialog';
    else if (after.hash !== probeBefore.hash) effect = 'DOM changed';
    else if (Math.abs(after.scrollY - probeBefore.scrollY) > 30) effect = 'scrolled';
    else effect = 'NO VISIBLE EFFECT';

    results.push({ page: `admin / ${tabLabel}`, button: target.name, effect, urlBefore: probeBefore.url, urlAfter: after.url, errors: takeErrors(sink2) });
  }

  results.push({ page: `admin / ${tabLabel}`, button: '__tab__', effect: `buttons=${targets.length}`, urlBefore: before.url, urlAfter: before.url, errors: takeErrors(sink) });
  return results;
}

async function runRole(
  browser: Browser,
  label: string,
  email: string,
  password: string,
): Promise<{ role: string; findings: ProbeResult[] }> {
  const context: BrowserContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.goto(BASE + '/admin-login', { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.waitForTimeout(1500);
  await login(page, email, password);

  const findings: ProbeResult[] = [];
  for (const tabLabel of TAB_LABELS) {
    if (!page.url().includes('/admin')) {
      await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded', timeout: 45_000 });
      await page.waitForTimeout(1200);
    }
    const tabButton = page.getByRole('button', { name: new RegExp(`^${tabLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }).first();
    if (!(await tabButton.isVisible().catch(() => false))) continue;
    findings.push(...(await mainTabProbe(page, tabLabel)));
  }

  await logout(page);
  await context.close();
  return { role: label, findings };
}

(async () => {
  const browser = await chromium.launch();
  const started = Date.now();
  const superadmin = await runRole(browser, 'superadmin', 'superadmin@taisartownhub.com', 'Super@Admin2026!');
  await new Promise((r) => setTimeout(r, 4000));
  const manager = await runRole(browser, 'manager', 'manager@taisartownhub.com', 'Manager@2026!');
  await new Promise((r) => setTimeout(r, 4000));
  const dealer = await runRole(browser, 'dealer', 'dealer@taisartownhub.com', 'Dealer@2026!');

  const report = {
    base: BASE,
    generatedAt: new Date().toISOString(),
    durationMs: Date.now() - started,
    results: [superadmin, manager, dealer],
  };
  writeFileSync(path.join(OUT, 'admin-interactions.json'), JSON.stringify(report, null, 2));

  console.log(`\nAdmin interaction audit complete in ${Math.round((Date.now() - started) / 1000)}s → ${path.join(OUT, 'admin-interactions.json')}`);
  for (const run of report.results) {
    const noEffect = run.findings.filter((f) => f.effect === 'NO VISIBLE EFFECT' && f.button !== '__tab__');
    const clickFailed = run.findings.filter((f) => f.effect.startsWith('CLICK FAILED'));
    const errorEntries = run.findings.filter((f) => f.errors.length > 0 && f.button !== '__tab__');
    const buttonCount = run.findings.filter((f) => f.button === '__tab__').map((f) => f.page);
    console.log(`\n[${run.role}] tabs probed=${buttonCount.length} noEffect=${noEffect.length} clickFailed=${clickFailed.length} errored=${errorEntries.length}`);
    noEffect.forEach((f) => console.log(`  NO EFFECT [${f.page}] "${f.button}"`));
    clickFailed.forEach((f) => console.log(`  CLICK FAILED [${f.page}] "${f.button}" → ${f.effect}`));
    errorEntries.forEach((f) => console.log(`  ERR [${f.page}] "${f.button}" → ${f.errors.slice(0, 3).join(' | ')}`));
  }
})();
