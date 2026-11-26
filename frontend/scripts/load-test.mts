/**
 * Load / concurrency harness (Phase 5).
 *
 * Drives realistic concurrent traffic against the running API and reports
 * latency percentiles, throughput and error rates per endpoint family. It
 * also runs a **concurrency integrity** check: N parallel transactions for
 * one disposable property must receive distinct deal numbers. Dealer-file
 * double-sell protection is covered separately by BookingConcurrencyTest.
 *
 * Usage:
 *   npx tsx scripts/load-test.mts [durationSeconds] [concurrency]
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';

const DURATION = Number(process.argv[2] || 20);
const CONCURRENCY = Number(process.argv[3] || 10);

// This harness creates disposable properties and transactions. Never let a
// forgotten command or a copied production URL mutate a real customer system.
const apiUrl = new URL(API);
const isLoopback = ['localhost', '127.0.0.1', '::1'].includes(apiUrl.hostname);
if (process.env.LOAD_TEST_ALLOW_MUTATIONS !== 'true') {
  throw new Error('Refusing to run: set LOAD_TEST_ALLOW_MUTATIONS=true only for an approved disposable test environment.');
}
if (!isLoopback && process.env.LOAD_TEST_ALLOW_REMOTE !== 'true') {
  throw new Error('Refusing a non-local API target. Use a disposable staging environment and explicitly set LOAD_TEST_ALLOW_REMOTE=true.');
}
if (!process.env.LOAD_TEST_EMAIL || !process.env.LOAD_TEST_PASSWORD) {
  throw new Error('Set LOAD_TEST_EMAIL and LOAD_TEST_PASSWORD for a dedicated disposable test account; built-in credentials are not allowed.');
}
if (!Number.isInteger(DURATION) || DURATION < 1 || DURATION > 600
  || !Number.isInteger(CONCURRENCY) || CONCURRENCY < 1 || CONCURRENCY > 100) {
  throw new Error('Duration must be 1..600 seconds and concurrency must be 1..100.');
}

type Sample = { ms: number; status: number; endpoint: string };
type Family = { name: string; samples: Sample[]; errors: number; throttled: number; serverErrors: number };

const families = new Map<string, Family>();
function record(endpoint: string, ms: number, status: number) {
  const family = endpoint.replace(/\?.*$/, '').replace(/\/[^/]+\/[^/]+$/, '/*');
  if (!families.has(family)) {
    families.set(family, { name: family, samples: [], errors: 0, throttled: 0, serverErrors: 0 });
  }
  const entry = families.get(family)!;
  entry.samples.push({ ms, status, endpoint });
  // HTTP 429 is an expected rate-limit outcome during a deliberate load run.
  if (status >= 400 && status !== 429) entry.errors += 1;
  if (status === 429) entry.throttled += 1;
  if (status >= 500) entry.serverErrors += 1;
}

async function timed(endpoint: string, init: RequestInit = {}) {
  const start = Date.now();
  try {
    const res = await fetch(`${API}${endpoint}`, init);
    await res.text();
    const ms = Date.now() - start;
    record(endpoint, ms, res.status);
    return res.status;
  } catch (error) {
    const ms = Date.now() - start;
    record(endpoint, ms, 0);
    return 0;
  }
}

function percentile(values: number[], p: number): number {
  if (values.length === 0) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length));
  return sorted[index];
}

const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

(async () => {
  // --- one authenticated session for the protected endpoints ---
  const login = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: jsonHeaders,
    body: JSON.stringify({ email: process.env.LOAD_TEST_EMAIL, password: process.env.LOAD_TEST_PASSWORD }),
  });
  const session = await login.json().catch(() => ({}));
  const token = session?.token;
  if (!login.ok || !token) {
    throw new Error(`Dedicated load-test account login failed (HTTP ${login.status}); no load or mutations were started.`);
  }
  const authHeaders = { ...jsonHeaders, Authorization: `Bearer ${token}` };

  console.log(`load test: ${DURATION}s, concurrency ${CONCURRENCY}, authenticated=true, target=${apiUrl.origin}`);

  const stopAt = Date.now() + DURATION * 1000;
  const publicEndpoints = ['/catalog', '/properties', '/projects', '/locations', '/videos', '/dealers'];
  const authEndpoints = ['/workspace', '/admin/dashboard', '/agency/transactions', '/notifications', '/admin/audit-logs'];

  let worker = 0;
  await Promise.all(
    Array.from({ length: CONCURRENCY }, async () => {
      worker += 1;
      while (Date.now() < stopAt) {
        const endpoint = publicEndpoints[Math.floor(Math.random() * publicEndpoints.length)];
        await timed(endpoint);
        if (token && Math.random() > 0.4) {
          const authEndpoint = authEndpoints[Math.floor(Math.random() * authEndpoints.length)];
          await timed(authEndpoint, { headers: authHeaders });
        }
      }
    }),
  );

  // --- concurrency integrity: allocate distinct deal numbers for parallel
  // transaction writes against one disposable property ---
  // The API throttles to 60 requests/minute per IP, so the load phase above
  // consumes the window. Cooldown first, otherwise every parallel attempt is
  // rejected by the throttle and the test proves nothing.
  const cooldown = Number(process.env.LOAD_COOLDOWN || 65);
  console.log(`\ncooling down ${cooldown}s so the throttle window is fresh for the booking test...`);
  await new Promise((r) => setTimeout(r, cooldown * 1000));

  // The booking target must belong to the caller's agency, otherwise the
  // tenant check rejects it (which is the isolation working correctly). Create
  // a dedicated listing for this run instead of reusing a seeded record.
  const probeProperty = await fetch(`${API}/properties`, {
    method: 'POST',
    headers: authHeaders,
    body: JSON.stringify({
      title: `Load-test property ${Date.now()}`,
      pricePKR: 12000000,
      phase: '1',
      sector: 'Sector 70-D',
      sizeSqYards: 120,
      type: 'Residential Plot',
    }),
  });
  const probePropertyBody = await probeProperty.json().catch(() => ({}));
  const propertyId = probePropertyBody?.property?.id || probePropertyBody?.id;
  if (!probeProperty.ok || !propertyId) {
    throw new Error(`Disposable property creation failed (HTTP ${probeProperty.status}); refusing to book any existing property.`);
  }
  console.log(`\nbooking target: ${propertyId} (created: ${probeProperty.status})`);
  const attempts = 8;
  const bookingResults = await Promise.all(
    Array.from({ length: attempts }, async (_, index) => {
      const start = Date.now();
      const res = await fetch(`${API}/agency/transactions`, {
        method: 'POST',
        headers: authHeaders,
        body: JSON.stringify({
          propertyId,
          propertyTitle: `Load-test concurrent booking ${Date.now()}-${index}`,
          sector: 'Sector 70-D',
          transactionType: 'Sale',
          sellerName: 'Load Test Seller',
          buyerName: `Load Test Buyer ${index}`,
          agreementAmountPKR: 10000000,
          dealStatus: 'Draft',
          internalRemarks: 'automated load-test booking attempt',
        }),
      });
      const ms = Date.now() - start;
      const body = await res.json().catch(() => ({}));
      record('/agency/transactions (booking)', ms, res.status);
      return { status: res.status, ms, body };
    }),
  );
  const created = bookingResults.filter((r) => r.status === 201).length;

  // --- report ---
  const all = [...families.values()].flatMap((f) => f.samples);
  const errors = all.filter((s) => s.status === 0 || (s.status >= 400 && s.status !== 429)).length;
  const throttled = all.filter((s) => s.status === 429).length;
  const serverErrors = all.filter((s) => s.status >= 500).length;
  const clientErrors = all.filter((s) => s.status >= 400 && s.status < 500 && s.status !== 429).length;
  const networkErrors = all.filter((s) => s.status === 0).length;
  const duration = DURATION;

  console.log('\nendpoint family                 n     rps   p50   p95   p99   max  429  5xx');
  console.log('------------------------------------------------------------------------------------');
  for (const family of families.values()) {
    const times = family.samples.map((s) => s.ms);
    const rps = family.samples.length / duration;
    console.log(
      `${family.name.padEnd(28)} ${String(family.samples.length).padStart(4)} ` +
        `${rps.toFixed(1).padStart(6)} ${String(percentile(times, 50)).padStart(5)} ` +
        `${String(percentile(times, 95)).padStart(5)} ${String(percentile(times, 99)).padStart(5)} ` +
        `${String(times.reduce((max, value) => Math.max(max, value), 0)).padStart(5)} ${String(family.throttled).padStart(4)} ` +
        `${String(family.serverErrors).padStart(5)}`,
    );
  }

  const times = all.map((s) => s.ms);
  console.log('\n--- totals ---');
  console.log(`requests          : ${all.length}`);
  console.log(`throughput        : ${(all.length / duration).toFixed(1)} req/s (concurrency ${CONCURRENCY})`);
  console.log(`latency p50/p95/p99/max : ${percentile(times, 50)} / ${percentile(times, 95)} / ${percentile(times, 99)} / ${times.reduce((max, value) => Math.max(max, value), 0)} ms`);
  console.log(`429 (throttled)   : ${throttled}`);
  console.log(`5xx (server bugs) : ${serverErrors}`);
  console.log(`4xx (client)      : ${clientErrors}`);
  console.log(`network failures  : ${networkErrors}`);
  console.log(`total error rate  : ${((errors / all.length) * 100).toFixed(2)}%`);
  if (serverErrors > 0 || networkErrors > 0) process.exitCode = 1;

  console.log('\n--- concurrency integrity (parallel transaction writes) ---');
  bookingResults.forEach((r, i) =>
    console.log(
      `  attempt ${i + 1}: status=${r.status} ${r.ms}ms ${r.status === 201 ? 'CREATED' : ''} ${
        r.status !== 201 ? JSON.stringify(r.body?.message || r.body?.errors || r.body).slice(0, 140) : ''
      }`,
    ),
  );
  const dealNumbers = bookingResults
    .filter((r) => r.status === 201)
    .map((r) => r.body?.transaction?.dealNumber || r.body?.dealNumber)
    .filter((value): value is string => typeof value === 'string' && value.length > 0);
  const uniqueDealNumbers = new Set(dealNumbers);
  const bookingIntegrityPass = bookingResults.every((r) => r.status === 201)
    && dealNumbers.length === created
    && uniqueDealNumbers.size === created;
  const unexpectedServerErrors = bookingResults.filter((r) => r.status === 0 || r.status >= 500).length;

  console.log(
    bookingIntegrityPass
      ? `PASS — ${created} parallel transactions received ${uniqueDealNumbers.size} unique deal numbers`
      : `FAIL — ${created}/${attempts} transactions created; ${dealNumbers.length} deal numbers, ${uniqueDealNumbers.size} unique`,
  );
  if (unexpectedServerErrors > 0 || !bookingIntegrityPass) process.exitCode = 1;

  console.log(JSON.stringify({
    generatedAt: new Date().toISOString(),
    duration,
    concurrency: CONCURRENCY,
    requests: all.length,
    throughputRps: Number((all.length / duration).toFixed(2)),
    p50: percentile(times, 50),
    p95: percentile(times, 95),
    p99: percentile(times, 99),
    max: Math.max(...times),
    errors,
    throttled,
    serverErrors,
    clientErrors,
    networkErrors,
    concurrentBookingAttempts: attempts,
    concurrentBookingCreated: created,
  }, null, 2));

  // --- cleanup (best effort): remove/void the disposable booking fixtures ---
  const createdIds = bookingResults
    .filter((r) => r.status === 201)
    .map((r) => r.body?.transaction?.id || r.body?.id || r.body?.data?.id)
    .filter(Boolean);
  for (const id of createdIds) {
    const res = await fetch(`${API}/agency/transactions/${id}/void`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({ reason: 'automated load-test cleanup' }),
    });
    console.log(`cleanup: void transaction ${id} -> ${res.status}`);
    if (!res.ok) process.exitCode = 1;
  }
  if (propertyId) {
    const res = await fetch(`${API}/properties/${propertyId}`, {
      method: 'DELETE',
      headers: authHeaders,
    });
    console.log(`cleanup: delete disposable load-test property ${propertyId} -> ${res.status}`);
    if (!res.ok) {
      console.error('WARNING: disposable load-test property cleanup failed; inspect the test environment before re-running.');
      process.exitCode = 1;
    }
  }
})();
