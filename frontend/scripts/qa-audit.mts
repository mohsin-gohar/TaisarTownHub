import { chromium, type ConsoleMessage, type Page } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:4173';
const OUT = process.env.QA_OUT_DIR || 'C:/Users/mohsi/AppData/Local/Temp/opencode/qa-artifacts';
const widths = [360, 390, 768, 1024, 1366, 1440];
const routes: Array<{ path: string; name: string }> = [
  { path: '/', name: 'home' },
  { path: '/properties', name: 'properties' },
  { path: '/properties?property=prop-1', name: 'property-detail' },
  { path: '/agencies', name: 'agencies' },
  { path: '/agencies/taisartownhub-premier-advisory', name: 'agency-profile' },
  { path: '/guide', name: 'guide' },
  { path: '/gallery', name: 'gallery' },
  { path: '/services', name: 'services' },
  { path: '/about', name: 'about' },
  { path: '/contact', name: 'contact' },
  { path: '/login', name: 'login' },
  { path: '/register', name: 'register' },
  { path: '/admin-login', name: 'admin-login' },
  { path: '/this-route-does-not-exist', name: 'not-found' },
];

interface PageAudit {
  route: string;
  width: number;
  title: string;
  h1: string[];
  canonical: string | null;
  robots: string | null;
  jsonLd: number;
  lang: string | null;
  consoleErrors: string[];
  pageErrors: string[];
  failedRequests: string[];
  horizontalOverflow: boolean;
  scrollWidth: number;
  innerWidth: number;
  imagesBroken: number;
  imagesTotal: number;
  textSample: string;
  screenshot?: string;
}

await mkdir(OUT, { recursive: true });
const browser = await chromium.launch();

async function audit(page: Page, route: string, width: number): Promise<PageAudit> {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const failedRequests: string[] = [];
  const onConsole = (msg: ConsoleMessage) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 400));
  };
  const onPageError = (error: Error) => pageErrors.push(String(error).slice(0, 400));
  const onFailed = (req: { url: () => string; failure: () => { errorText: string } | null }) => {
    failedRequests.push(`${req.url()} :: ${req.failure()?.errorText || 'failed'}`);
  };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);
  page.on('requestfailed', onFailed);

  await page.setViewportSize({ width, height: 900 });
  await page.goto(`${BASE}${route}`, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
  await page.waitForTimeout(1800);

  const data = await page.evaluate(() => {
    const doc = document.documentElement;
    const images = Array.from(document.images);
    return {
      title: document.title,
      h1: Array.from(document.querySelectorAll('h1')).map((el) => (el.textContent || '').trim()).filter(Boolean),
      canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href') ?? null,
      robots: document.querySelector('meta[name="robots"]')?.getAttribute('content') ?? null,
      jsonLd: document.querySelectorAll('script[type="application/ld+json"]').length,
      lang: doc.getAttribute('lang'),
      horizontalOverflow: doc.scrollWidth > window.innerWidth + 1,
      scrollWidth: doc.scrollWidth,
      innerWidth: window.innerWidth,
      imagesBroken: images.filter((img) => img.complete && img.naturalWidth === 0).length,
      imagesTotal: images.length,
      textSample: (document.body.innerText || '').slice(0, 400),
    };
  });

  page.off('console', onConsole);
  page.off('pageerror', onPageError);
  page.off('requestfailed', onFailed);

  return { route, width, ...data, consoleErrors, pageErrors, failedRequests };
}

const results: PageAudit[] = [];
for (const route of routes) {
  for (const width of widths) {
    const page = await browser.newPage();
    const result = await audit(page, route.path, width);
    if (width === 390 || width === 1366) {
      const file = path.join(OUT, `${route.name}-${width}.png`);
      await page.screenshot({ path: file, fullPage: false }).catch(() => {});
      result.screenshot = file;
    }
    results.push(result);
    await page.close();
  }
}

// Interaction checks (desktop).
const interaction: Record<string, any> = {};
{
  const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(String(error)));

  await page.goto(`${BASE}/properties?property=prop-1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  interaction.propertyDetail = {
    title: await page.title(),
    heading: await page.locator('h1, h2').first().innerText().catch(() => ''),
    hasPrice: /PKR/.test(await page.locator('main').innerText()),
    hasInquiryForm: await page.locator('input[type="tel"], input[placeholder*="phone" i], input[name*="phone" i]').count(),
    galleryImages: await page.locator('main img').count(),
    buttons: await page.locator('main button').count(),
  };
  const swiperNext = page.locator('button[aria-label*="next" i], button[aria-label*="Next" i]').first();
  if (await swiperNext.count()) {
    await swiperNext.click().catch(() => {});
    interaction.propertyDetail.galleryNextClicked = true;
  }

  await page.goto(`${BASE}/agencies`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  // Agency cards navigate via a button + history.pushState, not an <a href>.
  const agencyProfileButtons = page.getByRole('button', { name: /view agency profile/i });
  interaction.agencies = {
    heading: await page.locator('h1, h2').first().innerText().catch(() => ''),
    cards: await agencyProfileButtons.count(),
    text: (await page.locator('main').innerText()).slice(0, 800),
  };

  const firstAgency = agencyProfileButtons.first();
  if (await firstAgency.count()) {
    await firstAgency.click();
    await page.waitForTimeout(1800);
    interaction.agencyProfile = {
      url: page.url(),
      heading: await page.locator('h1, h2').first().innerText().catch(() => ''),
      listings: await page.locator('a[href*="/properties?property="]').count(),
      text: (await page.locator('main').innerText()).slice(0, 800),
    };
  }

  await page.goto(`${BASE}/gallery`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  // Video tours are clickable thumbnail divs (Play icon), not <video>/<iframe>
  // elements until one is opened, and phase filters are plain buttons.
  interaction.gallery = {
    heading: await page.locator('h1, h2').first().innerText().catch(() => ''),
    videos: await page.locator('main .aspect-video').count(),
    playButtons: await page.locator('main .aspect-video svg.lucide-play').count(),
    tabs: await page.getByRole('button', { name: /^(all video tours|phase \d)/i }).count(),
    text: (await page.locator('main').innerText()).slice(0, 600),
  };

  await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  interaction.contact = {
    inputs: await page.locator('main input, main textarea, main select').count(),
    submit: await page.getByRole('button', { name: /send|submit|inquiry/i }).count(),
    text: (await page.locator('main').innerText()).slice(0, 600),
  };

  interaction.pageErrors = errors;
  await page.close();
}

await browser.close();

const summary = {
  generatedAt: new Date().toISOString(),
  base: BASE,
  outDir: OUT,
  checks: results.length,
  consoleErrors: results.flatMap((r) => r.consoleErrors.map((e) => ({ route: r.route, width: r.width, error: e }))),
  pageErrors: results.flatMap((r) => r.pageErrors.map((e) => ({ route: r.route, width: r.width, error: e }))),
  failedRequests: results.flatMap((r) => r.failedRequests.map((e) => ({ route: r.route, width: r.width, error: e }))),
  overflow: results.filter((r) => r.horizontalOverflow).map((r) => ({ route: r.route, width: r.width, scrollWidth: r.scrollWidth, innerWidth: r.innerWidth })),
  brokenImages: results.filter((r) => r.imagesBroken > 0).map((r) => ({ route: r.route, width: r.width, broken: r.imagesBroken, total: r.imagesTotal })),
  seo: routes.map((route) => {
    const at1366 = results.find((r) => r.route === route.path && r.width === 1366);
    return at1366 ? {
      route: route.path,
      title: at1366.title,
      h1: at1366.h1,
      canonical: at1366.canonical,
      robots: at1366.robots,
      jsonLd: at1366.jsonLd,
      lang: at1366.lang,
    } : null;
  }),
  results,
  interaction,
};

await writeFile(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
console.log(JSON.stringify({
  outDir: OUT,
  checks: summary.checks,
  consoleErrorCount: summary.consoleErrors.length,
  pageErrorCount: summary.pageErrors.length,
  failedRequestCount: summary.failedRequests.length,
  overflow: summary.overflow,
  brokenImages: summary.brokenImages,
  seo: summary.seo,
  interaction,
}, null, 2));
