/**
 * Performance snapshot (Phase 12) — real timings against the running stack.
 */
import { chromium } from '@playwright/test';

const API = process.env.AUDIT_API || 'http://127.0.0.1:8090/api';
const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';

async function timeIt(label: string, fn: () => Promise<unknown>) {
  const samples: number[] = [];
  for (let i = 0; i < 5; i++) {
    const start = Date.now();
    await fn();
    samples.push(Date.now() - start);
  }
  samples.sort((a, b) => a - b);
  const avg = Math.round(samples.reduce((a, b) => a + b, 0) / samples.length);
  console.log(`${label.padEnd(34)} avg=${avg}ms  min=${samples[0]}ms  max=${samples[samples.length - 1]}ms`);
}

(async () => {
  console.log('--- API (5 samples each) ---');
  await timeIt('GET /api/health', () => fetch(`${API}/health`).then((r) => r.text()));
  await timeIt('GET /api/catalog', () => fetch(`${API}/catalog`).then((r) => r.text()));
  await timeIt('GET /api/properties (public)', () => fetch(`${API}/properties`).then((r) => r.text()));
  await timeIt('GET /api/locations', () => fetch(`${API}/locations`).then((r) => r.text()));
  await timeIt('POST /api/subscribers (throttled)', () =>
    fetch(`${API}/subscribers`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: `perf-${Date.now()}@example.test` }),
    }).then((r) => r.text()),
  );

  console.log('\n--- Frontend page loads (Chromium, cold cache per page) ---');
  const browser = await chromium.launch();
  for (const route of ['/', '/properties', '/projects', '/contact', '/login']) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const start = Date.now();
    await page.goto(BASE + route, { waitUntil: 'load', timeout: 60000 });
    const load = Date.now() - start;
    const metrics = await page.evaluate(() => {
      const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
      const paints = performance.getEntriesByType('paint');
      const fcp = paints.find((p) => p.name === 'first-contentful-paint');
      return {
        domContentLoaded: nav ? Math.round(nav.domContentLoadedEventEnd) : null,
        fcp: fcp ? Math.round(fcp.startTime) : null,
      };
    });
    console.log(
      `${route.padEnd(12)} load=${load}ms  DCL=${metrics.domContentLoaded}ms  FCP=${metrics.fcp}ms`,
    );
    await context.close();
  }
  await browser.close();
})();
