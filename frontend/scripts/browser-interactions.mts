/**
 * Browser interaction audit for the disposable full-stack CI environment.
 *
 * Goes beyond the passive crawl: for every public route it actually
 *   1. navigates every internal link and records the HTTP status,
 *   2. clicks every visible, enabled, non-destructive button and verifies
 *      that *something* happened (route change, DOM change, modal, scroll,
 *      popup, toast) — dead clicks are reported,
 *   3. opens the first property + project detail page and probes their
 *      buttons,
 *   4. submits the contact form, newsletter form, property inquiry and
 *      project booking form, and validates the login/register flows.
 *
 * Output: frontend/audit/interactions.json
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

/** Buttons whose click can destroy data — never auto-clicked. */
const DESTRUCTIVE = /delete|remove|sign out|log out|logout|deactivate|reject|decline|approve|mark (as )?(paid|resolved|closed)|pay now|refund|suspend|terminate|revoke|purge|archive|publish|unpublish|reset password|clear all|wipe/i;

type Sink = {
  consoleErrors: string[];
  pageErrors: string[];
  failedRequests: string[];
  httpErrors: string[];
};

type ButtonInfo = {
  index: number;
  name: string;
  type: string;
  disabled: boolean;
  expanded: string | null;
  visible: boolean;
  inForm: boolean;
};

type ProbeResult = {
  page: string;
  button: string;
  index: number;
  effect: string;
  urlBefore: string;
  urlAfter: string;
  errors: string[];
};

type LinkResult = {
  source: string;
  href: string;
  status: number | null;
  textLength: number;
  errors: string[];
};

type FormResult = {
  form: string;
  ok: boolean;
  detail: string;
  errors: string[];
};

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms));

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

function normalizeRoute(pathname: string): string {
  return pathname.replace(/\/+$/, '') || '/';
}

async function gotoRoute(page: Page, route: string): Promise<void> {
  await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.waitForTimeout(1200);
}

function normalizeName(raw: string): string {
  return raw.replace(/\s+/g, ' ').trim().slice(0, 90);
}

async function listButtons(page: Page): Promise<ButtonInfo[]> {
  return page.evaluate(() => {
    return Array.from(document.querySelectorAll('button')).map((el, index) => {
      const rect = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      const name = (el.getAttribute('aria-label') || el.innerText || el.getAttribute('title') || '')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 90);
      return {
        index,
        name,
        type: el.getAttribute('type') || 'submit',
        disabled: (el as HTMLButtonElement).disabled || el.getAttribute('aria-disabled') === 'true',
        expanded: el.getAttribute('aria-expanded'),
        visible:
          rect.width > 0 &&
          rect.height > 0 &&
          style.visibility !== 'hidden' &&
          style.display !== 'none' &&
          style.opacity !== '0',
        inForm: Boolean(el.closest('form')),
      };
    });
  });
}

async function snapshot(page: Page) {
  return page.evaluate(() => {
    const root = document.getElementById('root');
    const html = root?.innerHTML || '';
    let hash = 0;
    for (let i = 0; i < html.length; i += 1) hash = ((hash << 5) - hash + html.charCodeAt(i)) | 0;
    const overlays = document.querySelectorAll(
      '[role="dialog"], [aria-modal="true"], .fixed.inset-0',
    ).length;
    return {
      url: location.href,
      hash: String(hash),
      textLength: (root?.innerText || '').trim().length,
      overlays,
      scrollY: Math.round(window.scrollY),
      bodyOverflow: getComputedStyle(document.body).overflow,
    };
  });
}

async function dismissNativeDialogs(page: Page) {
  page.on('dialog', (dialog) => void dialog.dismiss().catch(() => {}));
}

async function auditLinks(page: Page, routes: string[]): Promise<LinkResult[]> {
  const results: LinkResult[] = [];
  const seen = new Set<string>();
  const candidates: { source: string; href: string }[] = [];

  for (const route of routes) {
    await gotoRoute(page, route);
    const anchors = await page.evaluate(() =>
      Array.from(document.querySelectorAll('a[href]')).map((a) => ({
        href: a.getAttribute('href') || '',
        text: (a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60),
      })),
    );
    for (const anchor of anchors) {
      const href = anchor.href.trim();
      if (!href || href === '#' || href.startsWith('javascript:')) {
        results.push({ source: route, href: href || '(missing)', status: null, textLength: 0, errors: ['DEAD HREF'] });
        continue;
      }
      if (href.startsWith('#')) {
        // In-page skip/anchor links: the target must exist on this route.
        const targetExists = await page.evaluate((id) => {
          try {
            return Boolean(document.getElementById(decodeURIComponent(id)));
          } catch {
            return false;
          }
        }, href.slice(1));
        if (!targetExists) {
          results.push({ source: route, href, status: null, textLength: 0, errors: ['MISSING ANCHOR TARGET'] });
        }
        continue;
      }
      if (/^(tel:|mailto:|https?:)/i.test(href)) {
        if (!/^(tel:\+?[0-9\s()-]+|mailto:[^\s@]+@[^\s@]+\.[^\s@]+|https?:\/\/[^\s]+)$/i.test(href)) {
          results.push({ source: route, href, status: null, textLength: 0, errors: ['MALFORMED HREF'] });
        }
        continue;
      }
      if (!href.startsWith('/')) {
        results.push({ source: route, href, status: null, textLength: 0, errors: ['NON-ROUTE HREF'] });
        continue;
      }
      if (seen.has(href)) continue;
      seen.add(href);
      candidates.push({ source: route, href });
    }
  }

  for (const candidate of candidates) {
    const sink = attachSink(page);
    const [pathPart, hashPart] = candidate.href.split('#');
    try {
      const response = await page.goto(BASE + pathPart, { waitUntil: 'domcontentloaded', timeout: 45_000 });
      await page.waitForTimeout(900);
      const status = response?.status() ?? null;
      const textLength = await page.evaluate(
        () => (document.getElementById('root')?.innerText || '').trim().length,
      );
      const anchorOk = hashPart
        ? await page.evaluate((id) => {
            try {
              return Boolean(document.getElementById(decodeURIComponent(id)));
            } catch {
              return false;
            }
          }, hashPart)
        : true;
      const errors = takeErrors(sink);
      if (hashPart && !anchorOk) errors.push(`MISSING ANCHOR TARGET #${hashPart}`);
      results.push({ source: candidate.source, href: candidate.href, status, textLength, errors });
    } catch (error) {
      results.push({
        source: candidate.source,
        href: candidate.href,
        status: null,
        textLength: 0,
        errors: [String(error).slice(0, 300), ...takeErrors(sink)],
      });
    }
  }

  return results;
}

async function probeButtons(page: Page, route: string, label: string): Promise<ProbeResult[]> {
  const results: ProbeResult[] = [];
  await gotoRoute(page, route);
  const buttons = await listButtons(page);

  const seen = new Set<string>();
  const targets: ButtonInfo[] = [];
  const visible = buttons.filter((b) => b.visible && !b.disabled);
  for (const button of visible) {
    if (!button.name) {
      results.push({
        page: label,
        button: '(unnamed button)',
        index: button.index,
        effect: 'SKIPPED — no accessible name (a11y issue)',
        urlBefore: route,
        urlAfter: route,
        errors: [],
      });
      continue;
    }
    // Only skip real form submits — most icon/CTA buttons inherit the HTML
    // default type="submit" without living inside a form.
    if (button.type === 'submit' && button.inForm) continue;
    if (DESTRUCTIVE.test(button.name)) continue;
    const key = /^save .* to favorites?/i.test(button.name)
      ? 'save to favorites'
      : /^remove .* from favorites?/i.test(button.name)
        ? 'remove from favorites'
        : button.name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);
    targets.push(button);
  }

  for (const target of targets) {
    const sink = attachSink(page);
    await gotoRoute(page, route);
    const before = await snapshot(page);

    // Re-resolve after reload: prefer accessible name, fall back to index.
    let locator = page.getByRole('button', { name: target.name, exact: true }).first();
    if (!(await locator.isVisible().catch(() => false))) {
      const fresh = await listButtons(page);
      const match = fresh.find((b) => b.visible && !b.disabled && b.name === target.name);
      if (!match) {
        results.push({
          page: label,
          button: target.name,
          index: target.index,
          effect: 'NOT FOUND AFTER RELOAD',
          urlBefore: before.url,
          urlAfter: before.url,
          errors: takeErrors(sink),
        });
        continue;
      }
      locator = page.locator('button').nth(match.index);
    }

    let popup: Page | null = null;
    const popupPromise = page.context().waitForEvent('page', { timeout: 700 }).catch(() => null);
    let clickFailed = '';
    try {
      await locator.click({ timeout: 6_000 });
    } catch (error) {
      clickFailed = String(error).slice(0, 200);
    }
    popup = await popupPromise;
    if (popup) await popup.close().catch(() => {});
    await delay(950);
    const after = await snapshot(page);
    const errors = takeErrors(sink);

    let effect = '';
    if (clickFailed) effect = `CLICK FAILED: ${clickFailed}`;
    else if (popup) effect = 'opened popup';
    else if (normalizeRoute(new URL(after.url).pathname) !== normalizeRoute(new URL(before.url).pathname)) {
      effect = 'route changed';
    } else if (after.url !== before.url) effect = 'hash/query changed';
    else if (after.overlays > before.overlays) effect = 'opened overlay/dialog';
    else if (after.bodyOverflow !== before.bodyOverflow) effect = 'body scroll lock changed';
    else if (after.hash !== before.hash) effect = 'DOM changed';
    else if (Math.abs(after.scrollY - before.scrollY) > 20) effect = 'scrolled';
    else effect = 'NO VISIBLE EFFECT';

    results.push({
      page: label,
      button: target.name,
      index: target.index,
      effect,
      urlBefore: before.url,
      urlAfter: after.url,
      errors,
    });
  }

  return results;
}

async function solveVisibleMathCaptcha(page: Page): Promise<void> {
  const question = page.locator('#inquiry-captcha-question').first();
  if (!(await question.isVisible().catch(() => false))) return;
  const text = await question.innerText();
  const match = text.match(/(\\d+)\\s*\\+\\s*(\\d+)/);
  if (!match) throw new Error(`Unsupported inquiry captcha question: ${text}`);
  const answer = page.locator('#inquiry-captcha-answer').first();
  await answer.fill(String(Number(match[1]) + Number(match[2])));
}

async function fillAndSubmit(
  page: Page,
  formLabel: string,
  route: string,
  fields: { label?: string; placeholder?: string; value: string }[],
  submitName: string | RegExp,
  successCheck: () => Promise<boolean>,
): Promise<FormResult> {
  const sink = attachSink(page);
  await gotoRoute(page, route);

  for (const field of fields) {
    let input;
    if (field.placeholder) input = page.getByPlaceholder(field.placeholder).first();
    else if (field.label) input = page.getByLabel(field.label).first();
    else continue;
    if (!(await input.isVisible().catch(() => false))) {
      return {
        form: formLabel,
        ok: false,
        detail: `field not found (${field.label || field.placeholder})`,
        errors: takeErrors(sink),
      };
    }
    await input.fill(field.value);
  }

  // Public inquiry forms require a signed, single-use math captcha.
  await solveVisibleMathCaptcha(page);

  const submit = page.getByRole('button', { name: submitName }).first();
  if (!(await submit.isVisible().catch(() => false))) {
    return { form: formLabel, ok: false, detail: 'submit button not found', errors: takeErrors(sink) };
  }
  await submit.click();
  await delay(1500);

  const ok = await successCheck();
  return { form: formLabel, ok, detail: ok ? 'success state shown' : 'no success state detected', errors: takeErrors(sink) };
}

async function runPublic(browser: Browser): Promise<{
  links: LinkResult[];
  probes: ProbeResult[];
  forms: FormResult[];
  details: { kind: string; url: string; probes: ProbeResult[]; errors: string[] }[];
  flows: FormResult[];
}> {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await dismissNativeDialogs(page);

  const routes = [
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
  ];

  const links = await auditLinks(page, routes);

  const probes: ProbeResult[] = [];
  const routeProbeTasks = routes.map(async (route) => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const probePage = await context.newPage();
    await dismissNativeDialogs(probePage);
    const results = await probeButtons(probePage, route, route);
    await context.close();
    return results;
  });
  const routeProbeGroups = await Promise.all(routeProbeTasks);
  for (const group of routeProbeGroups) probes.push(...group);

  // ---- detail pages ----
  const details: { kind: string; url: string; probes: ProbeResult[]; errors: string[] }[] = [];

  await gotoRoute(page, '/properties');
  const propertyCard = page.getByRole('button', { name: /View Details & Dossier/i }).first();
  if (await propertyCard.isVisible().catch(() => false)) {
    await propertyCard.click();
    await delay(1400);
    const url = page.url();
    const detailProbes = await probeButtons(
      page,
      new URL(url).pathname + new URL(url).search,
      'property detail',
    );
    details.push({ kind: 'property', url, probes: detailProbes, errors: [] });
  }

  await gotoRoute(page, '/projects');
  const projectCard = page.getByRole('button', { name: /View Project/i }).first();
  if (await projectCard.isVisible().catch(() => false)) {
    await projectCard.click();
    await delay(1400);
    const url = page.url();
    const detailProbes = await probeButtons(
      page,
      new URL(url).pathname + new URL(url).search,
      'project detail',
    );
    details.push({ kind: 'project', url, probes: detailProbes, errors: [] });
  }

  // ---- forms ----
  const forms: FormResult[] = [];

  forms.push(
    await fillAndSubmit(
      page,
      'contact form',
      '/contact',
      [
        { placeholder: 'e.g. Tariq Siddiqui', value: 'Browser Test User' },
        { placeholder: 'e.g. +92 300 1234567', value: '+923001234567' },
        { placeholder: 'e.g. investor@gmail.com', value: 'browser.test@taisartownhub.com' },
        { placeholder: 'e.g. Sector 70-D, 120 Sq Yd West Open Plot', value: 'Sector 70-D 120 Sq Yd' },
        { placeholder: 'Please mention your preferred location, whether you require ready possession, or if you wish to book an accompanied Sunday site visit...', value: 'Automated browser audit inquiry — please ignore.' },
      ],
      /Submit Official Inquiry/i,
      async () =>
        (await page.getByText(/Inquiry Registered Successfully/i).count()) > 0 ||
        (await page.getByText(/inquiry has been dispatched/i).count()) > 0,
    ),
  );

  // Contact-form validation: empty submit should surface an error, not a success.
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/contact');
    const submit = page.getByRole('button', { name: /Submit Official Inquiry/i }).first();
    await submit.click();
    await delay(700);
    // The form marks required fields with the native attribute; a prevented
    // submit leaves them :invalid and the browser shows its validation bubble.
    const nativeInvalid = await page.locator('form input:invalid, form textarea:invalid').count();
    const errorVisible =
      nativeInvalid > 0 ||
      (await page.getByText(/required|fill out/i).count()) > 0 ||
      (await page.locator('[role="alert"]').count()) > 0;
    forms.push({
      form: 'contact validation (empty)',
      ok: errorVisible,
      detail: errorVisible ? 'validation error shown' : 'no validation error shown',
      errors: takeErrors(sink),
    });
  }

  // Newsletter (footer)
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/');
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await delay(600);
    const email = page.getByPlaceholder('Enter your email').first();
    if (!(await email.isVisible().catch(() => false))) {
      forms.push({ form: 'newsletter', ok: false, detail: 'email input not found', errors: takeErrors(sink) });
    } else {
      await email.fill(`audit-${Date.now()}@taisartownhub.com`);
      await page.locator('button[aria-label="Subscribe to newsletter"]').click();
      await delay(1400);
      const ok = (await page.getByText(/subscrib|thank|added/i).count()) > 0;
      forms.push({ form: 'newsletter', ok, detail: ok ? 'success feedback shown' : 'no success feedback', errors: takeErrors(sink) });
    }
  }

  // Property inquiry
  if (details.some((d) => d.kind === 'property')) {
    const url = details.find((d) => d.kind === 'property')!.url;
    forms.push(
      await fillAndSubmit(
        page,
        'property inquiry',
        new URL(url).pathname + new URL(url).search,
        [
          { placeholder: 'e.g. Asad Siddiqui', value: 'Browser Test User' },
          { placeholder: 'e.g. +92 300 0000000', value: '+923001234567' },
        ],
        /Send Property Inquiry/i,
        async () => (await page.getByText(/inquiry has been dispatched/i).count()) > 0,
      ),
    );
  }

  // Project booking request
  if (details.some((d) => d.kind === 'project')) {
    const url = details.find((d) => d.kind === 'project')!.url;
    forms.push(
      await fillAndSubmit(
        page,
        'project booking',
        new URL(url).pathname + new URL(url).search,
        [
          { placeholder: 'e.g. Asad Farooqui', value: 'Browser Test User' },
          { placeholder: 'e.g. +92 321 0000000', value: '+923001234567' },
        ],
        /Send Booking Request/i,
        async () => (await page.getByText(/inquiry has been dispatched/i).count()) > 0,
      ),
    );
  }

  // ---- login / register flows ----
  const flows: FormResult[] = [];

  // Invalid login → visible error, no redirect.
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/login');
    const form = page.locator('form').filter({ has: page.locator('input[placeholder="Enter password"]') }).first();
    await form.locator('input[type="email"]').fill('not-a-user@taisartownhub.com');
    await form.locator('input[type="password"]').fill((process.env.DEMO_STAFF_PASSWORD || 'WrongPass123!'));
    await form.locator('button[type="submit"]').click();
    await delay(1800);
    const errorVisible =
      (await page.locator('[role="alert"]').count()) > 0 ||
      (await page.getByText(/invalid|incorrect|not found|failed|credentials/i).count()) > 0;
    flows.push({
      form: 'login invalid credentials',
      ok: errorVisible,
      detail: errorVisible ? 'error surfaced' : 'no error surfaced',
      errors: takeErrors(sink),
    });
  }

  // Valid login → dashboard.
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/login');
    const form = page.locator('form').filter({ has: page.locator('input[placeholder="Enter password"]') }).first();
    await form.locator('input[type="email"]').fill('superadmin@taisartownhub.com');
    await form.locator('input[type="password"]').fill(process.env.DEMO_STAFF_PASSWORD || 'Super@Admin2026!');
    await form.locator('button[type="submit"]').click();
    await delay(3000);
    const ok =
      page.url().includes('admin') ||
      (await page.getByText(/Admin ERP|Dashboard Overview|Agency Control Center/i).count()) > 0;
    flows.push({
      form: 'login valid superadmin',
      ok,
      detail: ok ? `reached ${page.url()}` : `did not reach dashboard (${page.url()})`,
      errors: takeErrors(sink),
    });

    // Sign out via the admin sidebar so subsequent flows start anonymous.
    if (ok) {
      const signOut = page.getByRole('button', { name: /Sign Out/i }).first();
      if (await signOut.isVisible().catch(() => false)) {
        await signOut.click();
        await delay(1200);
      }
    }
  }

  // Register validation: empty submit + password mismatch must not create an account.
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/register');
    await page.getByRole('button', { name: /Register an agency/i }).click().catch(() => {});
    await delay(600);
    const registerForm = page.locator('form').filter({ has: page.locator('input[placeholder="Min 8 characters"]') }).first();
    if (!(await registerForm.isVisible().catch(() => false))) {
      flows.push({ form: 'register validation', ok: false, detail: 'register form not visible', errors: takeErrors(sink) });
    } else {
      await registerForm.locator('button[type="submit"]').click();
      await delay(800);
      const emptyError = (await page.locator('[role="alert"]').count()) > 0 || (await page.getByText(/required|accept|must/i).count()) > 0;
      const fields = registerForm.locator('input');
      const count = await fields.count();
      if (count >= 6) {
        await fields.nth(0).fill('Audit Tester');
        await fields.nth(1).fill('0300-1234567');
        await fields.nth(2).fill(`audit-${Date.now()}@taisartownhub.com`);
        await fields.nth(3).fill('Sector 70-D, Taiser Town');
        await fields.nth(4).fill('Audit Test Agency');
        await fields.nth(5).fill('AuditPass123!');
        await fields.nth(6).fill('DifferentPass123!').catch(() => {});
      }
      const checkboxes = registerForm.locator('input[type="checkbox"]');
      for (let i = 0; i < (await checkboxes.count()); i += 1) await checkboxes.nth(i).check().catch(() => {});
      await registerForm.locator('button[type="submit"]').click();
      await delay(1000);
      const mismatchError = (await page.getByText(/do not match|password.*match/i).count()) > 0;
      flows.push({
        form: 'register validation',
        ok: emptyError && mismatchError,
        detail: `empty=${emptyError ? 'error' : 'NO ERROR'}, mismatch=${mismatchError ? 'error' : 'NO ERROR'}`,
        errors: takeErrors(sink),
      });
    }
  }

  // ---- search / filter behaviour ----
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/properties');
    // The card CTA is an <a> (role=link), not a button.
    const countCards = () => page.getByRole('link', { name: /View Details & Dossier/i }).count();
    const before = await countCards();
    await page.locator('select').nth(0).selectOption({ label: 'House / Villa' });
    await delay(500);
    const filtered = await countCards();
    await page.getByRole('button', { name: /Reset Filters/i }).click();
    await delay(500);
    const reset = await countCards();
    flows.push({
      form: 'property type filter + reset',
      ok: before > 0 && filtered !== before && reset === before,
      detail: `before=${before}, filtered=${filtered}, reset=${reset}`,
      errors: takeErrors(sink),
    });
  }

  {
    const sink = attachSink(page);
    await gotoRoute(page, '/properties');
    const search = page.getByPlaceholder('Plot number, sector, road...').first();
    const before = await page.getByRole('link', { name: /View Details & Dossier/i }).count();
    await search.fill('Sector 70-D');
    await delay(700);
    const filtered = await page.getByRole('link', { name: /View Details & Dossier/i }).count();
    const text = await page.locator('#main-content').innerText();
    flows.push({
      form: 'property keyword search',
      ok: filtered > 0 && filtered !== before && /Sector 70-D/i.test(text),
      detail: `before=${before}, filtered=${filtered}`,
      errors: takeErrors(sink),
    });
  }

  {
    const sink = attachSink(page);
    await gotoRoute(page, '/');
    const heroForm = page
      .locator('form')
      .filter({ has: page.getByRole('button', { name: 'Search', exact: true }) })
      .first();
    await heroForm.locator('select').nth(2).selectOption({ label: 'Sector 70-D (Phase 1)' });
    await heroForm.getByRole('button', { name: 'Search', exact: true }).click();
    await delay(1200);
    const text = await page.locator('#main-content').innerText();
    const ok = new URL(page.url()).pathname === '/properties' && /Sector 70-D/i.test(text);
    flows.push({
      form: 'hero search → properties',
      ok,
      detail: `url=${page.url()}, sectorText=${/Sector 70-D/i.test(text)}`,
      errors: takeErrors(sink),
    });
  }

  // ---- unknown URL renders a real 404, noindex, and recovers ----
  {
    const sink = attachSink(page);
    await gotoRoute(page, '/definitely-not-a-real-page-xyz');
    const text = await page.locator('#main-content').innerText();
    const robots = await page.locator('meta[name="robots"]').getAttribute('content').catch(() => '');
    const title = await page.title();
    const homeButton = page.getByRole('link', { name: /Back to Home/i }).first();
    const has404 = /404|not.*found|off the map/i.test(text);
    let recovered = false;
    if (await homeButton.isVisible().catch(() => false)) {
      await homeButton.click();
      await delay(800);
      const homeText = await page.locator('#main-content').innerText();
      // The hero headline is CMS-driven, so assert on a stable marker: the
      // pathname plus a richly populated homepage (nav + sections).
      recovered = new URL(page.url()).pathname === '/' && homeText.length > 300;
    }
    flows.push({
      form: 'unknown URL → 404',
      ok: has404 && /noindex/i.test(robots || '') && /404/i.test(title) && recovered,
      detail: `404=${has404}, robots=${robots}, title="${title}", recovery=${recovered}`,
      errors: takeErrors(sink),
    });
  }

  // Mobile: nav drawer opens, closes, and every menu link remains reachable.
  {
    const mobileContext = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const mobilePage = await mobileContext.newPage();
    const sink = attachSink(mobilePage);
    await gotoRoute(mobilePage, '/');
    const overflow = await mobilePage.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    const menu = mobilePage.getByRole('button', { name: /Open navigation menu/i }).first();
    const menuVisible = await menu.isVisible().catch(() => false);
    if (menuVisible) {
      await menu.click();
      await delay(600);
    }
    const drawer = mobilePage.locator('#mobile-nav-drawer');
    const drawerVisible = await drawer.isVisible().catch(() => false);
    const drawerLinks = drawerVisible ? await drawer.locator('a').count() : 0;
    const close = mobilePage.getByRole('button', { name: /Close menu/i }).first();
    if (await close.isVisible().catch(() => false)) {
      await close.click();
      await delay(400);
    }
    const drawerClosed = !(await drawer.isVisible().catch(() => false));
    flows.push({
      form: 'mobile navigation drawer',
      ok: menuVisible && drawerVisible && drawerLinks > 5 && drawerClosed && overflow <= 1,
      detail: `menu=${menuVisible}, drawer=${drawerVisible}, links=${drawerLinks}, closed=${drawerClosed}, horizontalOverflow=${overflow}px`,
      errors: takeErrors(sink),
    });
    await mobileContext.close();
  }

  await context.close();
  return { links, probes, forms, details, flows };
}

(async () => {
  const browser = await chromium.launch();
  const started = Date.now();
  const result = await runPublic(browser);
  await browser.close();

  const report = { base: BASE, generatedAt: new Date().toISOString(), durationMs: Date.now() - started, ...result };
  writeFileSync(path.join(OUT, 'interactions.json'), JSON.stringify(report, null, 2));

  const noEffect = result.probes.filter((p) => p.effect === 'NO VISIBLE EFFECT');
  const clickFailed = result.probes.filter((p) => p.effect.startsWith('CLICK FAILED') || p.effect === 'NOT FOUND AFTER RELOAD');
  const errorProbes = result.probes.filter((p) => p.errors.length > 0);
  const brokenLinks = result.links.filter((l) => (l.status !== null && l.status >= 400) || l.errors.length > 0);
  const failedForms = [...result.forms, ...result.flows].filter((f) => !f.ok);

  console.log(`\nInteraction audit complete in ${Math.round((Date.now() - started) / 1000)}s → ${path.join(OUT, 'interactions.json')}`);
  console.log(`\nLinks checked: ${result.links.length}, broken: ${brokenLinks.length}`);
  brokenLinks.forEach((l) => console.log(`  [${l.source}] ${l.href} → status=${l.status} ${l.errors.join(' | ')}`));
  console.log(`\nButtons probed: ${result.probes.length}, no-effect: ${noEffect.length}, click problems: ${clickFailed.length}, with errors: ${errorProbes.length}`);
  noEffect.forEach((p) => console.log(`  NO EFFECT [${p.page}] "${p.button}" (index ${p.index})`));
  clickFailed.forEach((p) => console.log(`  CLICK [${p.page}] "${p.button}" → ${p.effect}`));
  errorProbes.forEach((p) => console.log(`  ERR [${p.page}] "${p.button}" → ${p.errors.join(' | ')}`));
  console.log(`\nDetails opened: ${result.details.map((d) => `${d.kind}=${d.url}`).join(', ')}`);
  result.details.forEach((d) => {
    const bad = d.probes.filter((p) => p.effect === 'NO VISIBLE EFFECT' || p.effect.startsWith('CLICK FAILED'));
    if (bad.length) {
      console.log(`  [${d.kind}] ${bad.length} problem button(s):`);
      bad.forEach((p) => console.log(`    "${p.button}" → ${p.effect}`));
    }
  });
  console.log(`\nForms / flows: ${result.forms.length + result.flows.length}, failed: ${failedForms.length}`);
  failedForms.forEach((f) => console.log(`  FAIL [${f.form}] ${f.detail} ${f.errors.join(' | ')}`));
  [...result.forms, ...result.flows].forEach((f) => console.log(`  ${f.ok ? 'OK  ' : 'FAIL'} ${f.form} — ${f.detail}`));

  if (brokenLinks.length || noEffect.length || clickFailed.length || errorProbes.length || failedForms.length) {
    process.exitCode = 1;
  }
})();
