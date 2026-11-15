/**
 * Concurrent booking / double-sell race test.
 *
 * The binding business action is selling a dealer file (a property file) to a
 * buyer. The API guards it with `lockForUpdate()` + a status re-check, so two
 * simultaneous sells of the SAME file must never both succeed.
 *
 * This drives the real HTTP endpoints as the dealer account and fires N
 * parallel sell attempts for one file.
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';
const EMAIL = process.env.DEALER_EMAIL || 'dealer@taisartownhub.com';
const PASSWORD = process.env.DEALER_PASSWORD || 'Dealer@2026';
const ATTEMPTS = 8;

const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

async function call(path: string, options: any = {}) {
  const headers = { ...jsonHeaders, ...(options.headers || {}) };
  const res = await fetch(`${API}${path}`, {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const text = await res.text();
  let json: any = null;
  try {
    json = JSON.parse(text);
  } catch {
    json = null;
  }
  return { status: res.status, json, text };
}

(async () => {
  const login = await call('/auth/login', {
    method: 'POST',
    body: { email: EMAIL, password: PASSWORD },
  });
  if (login.status !== 200 || !login.json?.token) {
    console.log(`dealer sign-in failed: status=${login.status} ${login.text.slice(0, 160)}`);
    process.exit(1);
  }
  const headers = { Authorization: `Bearer ${login.json.token}` };
  console.log(`dealer signed in: ${login.json.user?.email} (role=${login.json.user?.role})`);

  const created = await call('/dealer-files', {
    method: 'POST',
    headers,
    body: {
      title: `Concurrency probe file ${Date.now()}`,
      type: 'pdf',
      url: 'https://example.test/concurrency-probe.pdf',
      folder: 'Documents',
      file_size_kb: 128,
    },
  });
  const fileId = created.json?.data?.id || created.json?.id || created.json?.file?.id;
  console.log(`probe file created: status=${created.status} id=${fileId}`);
  if (!fileId) {
    console.log(created.text.slice(0, 200));
    process.exit(1);
  }

  const start = Date.now();
  const results = await Promise.all(
    Array.from({ length: ATTEMPTS }, async (_, index) => {
      const t0 = Date.now();
      const res = await call(`/dealer-files/${fileId}/sell`, {
        method: 'POST',
        headers,
        body: {
          selling_price_kbr: 15000 + index,
          buyer_name: `Concurrent Buyer ${index}`,
          buyer_phone: '03001234567',
          buyer_cnic: '42101-1234567-1',
        },
      });
      return { status: res.status, ms: Date.now() - t0, body: res.json };
    }),
  );
  const elapsed = Date.now() - start;

  const succeeded = results.filter((r) => r.status === 200);
  const rejected = results.filter((r) => r.status !== 200);

  console.log(`\n${ATTEMPTS} parallel sell attempts for one file (wall clock ${elapsed} ms):`);
  results.forEach((r, i) =>
    console.log(
      `  attempt ${i + 1}: status=${r.status} ${r.ms}ms ${
        r.status === 200 ? 'SOLD' : `rejected: ${JSON.stringify(r.body?.message || r.body?.errors || r.body).slice(0, 110)}`
      }`,
    ),
  );

  const state = await call(`/dealer-files`, { headers });
  const fileState = Array.isArray(state.json)
    ? state.json.find((f: any) => f.id === fileId)
    : null;

  console.log(`\nfinal file state: status=${fileState?.status} buyer=${fileState?.buyer_name ?? '—'}`);
  console.log(
    succeeded.length === 1
      ? `PASS — exactly one of ${ATTEMPTS} parallel sells succeeded (${rejected.length} rejected); the file was sold once.`
      : `FAIL — ${succeeded.length} parallel sells succeeded`,
  );

  if (succeeded.length !== 1) process.exitCode = 1;
})();
