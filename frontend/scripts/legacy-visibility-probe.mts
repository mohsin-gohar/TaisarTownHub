/**
 * Legacy data visibility probe (Phase 3 — multi-tenant data migration).
 *
 * Creates two active tenants and compares what each of them receives from
 * `GET /api/workspace`. Records visible to *both* tenants are shared legacy
 * rows (pre-tenancy `business_records` without an agency stamp) — the exact
 * set a tenant migration must backfill.
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';

async function call(path: string, options: any = {}) {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (options.body) {
    headers['Content-Type'] = 'application/json';
  }
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
  return { status: res.status, json };
}

async function callWithBackoff(path: string, options: any = {}, attempts = 3) {
  let response = await call(path, options);
  for (let i = 0; i < attempts && response.status === 429; i++) {
    console.log(`   (429 on ${path} — waiting)`);
    await new Promise((r) => setTimeout(r, 22000));
    response = await call(path, options);
  }
  return response;
}

const stamp = Date.now();

function indexWorkspace(body: any): Map<string, Set<string>> {
  const map = new Map<string, Set<string>>();
  const payload = body?.workspace || body?.data || body || {};
  for (const [collection, rows] of Object.entries(payload)) {
    if (!Array.isArray(rows)) continue;
    const ids = new Set<string>();
    for (const row of rows) {
      const id = String(row?.id ?? row?.record_id ?? '');
      if (id) ids.add(id);
    }
    map.set(collection, ids);
  }
  return map;
}

(async () => {
  const admin = await call('/auth/login', {
    method: 'POST',
    body: { email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  });
  const adminToken = admin.json?.token;

  const tenants: Array<{ token: string; agencyId: string; label: string }> = [];
  for (const label of ['A', 'B']) {
    const email = `vis.${label.toLowerCase()}.${stamp}@audit.test`;
    const reg = await callWithBackoff('/agency/register', {
      method: 'POST',
      body: {
        name: `Visibility Probe ${label} ${stamp}`,
        contactName: `Vis Owner ${label}`,
        email,
        password: 'VisPass123!',
        phone: '03001234567',
      },
    });
    const agencyId = reg.json?.agency?.id;
    if (!agencyId) {
      console.log('registration failed', reg.status, reg.json);
      process.exit(1);
    }
    await call(`/admin/agencies/${agencyId}/verify`, {
      method: 'PUT',
      token: adminToken,
      body: { verification_status: 'verified' },
    });
    const login = await callWithBackoff('/auth/login', {
      method: 'POST',
      body: { email, password: 'VisPass123!' },
    });
    tenants.push({ token: login.json?.token || '', agencyId, label });
    console.log(`tenant ${label}: agency=${agencyId} login=${login.status}`);
  }

  const workspaces = await Promise.all(
    tenants.map(async (t) => {
      const res = await call('/workspace', { token: t.token });
      return { label: t.label, agencyId: t.agencyId, status: res.status, index: indexWorkspace(res.json) };
    }),
  );

  const [a, b] = workspaces;
  console.log(`\nworkspace status: A=${a.status} B=${b.status}`);
  console.log('collection'.padEnd(18), 'A', 'B', 'shared', 'only-A', 'only-B');

  const shared: Record<string, string[]> = {};
  const onlyA: Record<string, string[]> = {};
  const onlyB: Record<string, string[]> = {};

  const collections = new Set([...a.index.keys(), ...b.index.keys()]);
  for (const collection of collections) {
    const idsA = a.index.get(collection) || new Set<string>();
    const idsB = b.index.get(collection) || new Set<string>();
    const both = [...idsA].filter((id) => idsB.has(id));
    const onlyAIds = [...idsA].filter((id) => !idsB.has(id));
    const onlyBIds = [...idsB].filter((id) => !idsA.has(id));
    if (both.length) shared[collection] = both;
    if (onlyAIds.length) onlyA[collection] = onlyAIds;
    if (onlyBIds.length) onlyB[collection] = onlyBIds;
    console.log(
      collection.padEnd(18),
      String(idsA.size).padStart(3),
      String(idsB.size).padStart(3),
      String(both.length).padStart(6),
      String(onlyAIds.length).padStart(6),
      String(onlyBIds.length).padStart(6),
    );
  }

  console.log('\nshared record ids (visible to every tenant):');
  console.log(JSON.stringify(shared, null, 2));
  console.log('\ntenant-exclusive record ids:');
  console.log(JSON.stringify({ onlyA, onlyB }, null, 2));

  console.log(JSON.stringify({ generatedAt: new Date().toISOString(), shared, onlyA, onlyB }, null, 2));
})();
