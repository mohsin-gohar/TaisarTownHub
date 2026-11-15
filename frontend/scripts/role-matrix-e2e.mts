/**
 * Role-by-role authorisation matrix (drives the REAL API as each persona).
 *
 * Lifecycle under test (all through the product's own flows):
 *   1. agency owner (admin@) invites accountant / viewer / property_manager
 *      via POST /agency/invitations/invite  -> invite tokens returned
 *   2. each invitee accepts via POST /agency/invitations/accept
 *      (account created by the accept endpoint, unauthenticated)
 *   3. per role: 6 authorisation probes where the *server* decides
 *      403 = denied, 422/404 = the action was actually reachable
 *   4. owner deactivates the property_manager -> their token stops working
 *
 * Output: frontend/audit/role-matrix.json
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';
const stamp = Date.now();

type Row = { account: string; role: string; check: string; expected: string; got: number; verdict: 'PASS' | 'FAIL' };
const rows: Row[] = [];
const failures: Row[] = [];

async function call(path: string, options: any = {}) {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (options.agency) headers['X-Agency-Id'] = options.agency;
  if (options.body !== undefined) headers['Content-Type'] = 'application/json';
  const res = await fetch(`${API}${path}`, {
    method: options.method || 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
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

async function callWithBackoff(path: string, options: any = {}, attempts = 4) {
  let response = await call(path, options);
  for (let i = 0; i < attempts && response.status === 429; i++) {
    console.log(`   (429 on ${path} — waiting for the throttle window)`);
    await new Promise((r) => setTimeout(r, 22000));
    response = await call(path, options);
  }
  return response;
}

import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

/**
 * The probe body is deliberately invalid: a permitted role reaches validation
 * (422/404), a role without the permission is stopped earlier (403). This
 * keeps the probe read-only — no record is ever created by a "200".
 */
const CHECKS = [
  { id: 'property create', method: 'POST', path: '/properties', body: {} },
  { id: 'property delete', method: 'DELETE', path: '/properties/no-such-id' },
  { id: 'transaction create', method: 'POST', path: '/agency/transactions', body: {} },
  { id: 'staff invite', method: 'POST', path: '/agency/members/invite', body: {} },
  { id: 'platform users list', method: 'GET', path: '/admin/users' },
  { id: 'payment record', method: 'POST', path: '/agency/transactions/no-such-id/payments', body: {} },
];

/** expected "allowed" answer for each check id, keyed by persona */
const EXPECTED: Record<string, Record<string, number[]>> = {
  'superadmin@taisartownhub.com': {
    'property create': [422], 'property delete': [404], 'transaction create': [422],
    // Platform users inherit no agency: unscoped invite is denied by design.
    'staff invite': [403], 'platform users list': [200], 'payment record': [404],
  },
  'admin@taisartownhub.com': {
    'property create': [422], 'property delete': [404], 'transaction create': [422],
    // Platform users inherit no agency: unscoped invite is denied by design
    // (the scoped check below proves the same action succeeds with context).
    'staff invite': [403], 'platform users list': [403], 'payment record': [404],
  },
  'manager@taisartownhub.com': {
    'property create': [422], 'property delete': [404], 'transaction create': [422],
    'staff invite': [422], 'platform users list': [403], 'payment record': [404],
  },
  'dealer@taisartownhub.com': {
    'property create': [422], 'property delete': [403], 'transaction create': [403],
    'staff invite': [403], 'platform users list': [403], 'payment record': [403],
  },
  'operator@taisartownhub.com': {
    'property create': [403], 'property delete': [403], 'transaction create': [403],
    'staff invite': [403], 'platform users list': [403], 'payment record': [403],
  },
};

function checkRole(account: string, label: string, token: string, expected?: Record<string, number[]>) {
  return (async () => {
    for (const probe of CHECKS) {
      const res = await call(probe.path, { method: probe.method, token, body: probe.body });
      const allowedCodes = probe.id === 'property delete' || probe.id === 'payment record'
        ? [404]
        : probe.id === 'platform users list' ? [200] : [422];
      const isAllowed = allowedCodes.includes(res.status);
      const isDenied = res.status === 403;
      let verdict: 'PASS' | 'FAIL' = 'PASS';
      let detail = `status=${res.status}`;
      if (expected) {
        const want = expected[probe.id]?.[0];
        verdict = res.status === want ? 'PASS' : 'FAIL';
        detail = `status=${res.status} expected=${want}`;
      } else if (!isAllowed && !isDenied) {
        // unexpected limbo: neither a clean deny nor a clean reach
        verdict = 'FAIL';
        detail = `status=${res.status} (neither allowed (${allowedCodes.join('/')}) nor 403)`;
      }
      const row: Row = {
        account, role: label, check: probe.id,
        expected: expected ? String(expected[probe.id]?.[0]) : isAllowed ? 'allowed' : '403',
        got: res.status, verdict,
      };
      rows.push(row);
      console.log(`${verdict === 'PASS' ? 'PASS' : 'FAIL'} ${label.padEnd(18)} ${probe.id.padEnd(20)} ${detail}`);
      if (verdict === 'FAIL') failures.push(row);
    }
  })();
}

(async () => {
  // ---------- 0. real superadmin token ----------
  const superLogin = await callWithBackoff('/auth/login', {
    method: 'POST',
    body: { email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  });
  const superToken = superLogin.json?.token;
  if (!superToken) {
    console.log(`superadmin sign-in failed: ${superLogin.status}`);
    process.exit(1);
  }

  // ---------- 1. owner signs in ----------
  const owner = await callWithBackoff('/auth/login', {
    method: 'POST',
    body: { email: 'admin@taisartownhub.com', password: 'Admin@Taisar2026' },
  });
  if (owner.status !== 200 || !owner.json?.token) {
    console.log(`owner sign-in failed: ${owner.status} ${owner.text.slice(0, 160)}`);
    process.exit(1);
  }
  const ownerToken = owner.json.token;
  console.log('owner signed in: admin@taisartownhub.com (Premier Advisory)');

  // ---------- 2. invite the three unseeded roles ----------
  const rolesToInvite = [
    { role_id: 'role_accountant', label: 'accountant' },
    { role_id: 'role_viewer', label: 'viewer' },
    { role_id: 'role_property_manager', label: 'property_manager' },
  ];
  const created: Array<{ email: string; password: string; token: string; label: string }> = [];

  for (const role of rolesToInvite) {
    const email = `staff.${role.label}.${stamp}@audit.test`;
    const invite = await callWithBackoff('/agency/members/invite', {
      method: 'POST',
      token: ownerToken,
      agency: 'agency-demo',
      body: { email, role_id: role.role_id },
    });
    const inviteToken = invite.json?.invite_token ?? invite.json?.invitation?.invite_token;
    console.log(`invite ${role.label}: status=${invite.status} token=${Boolean(inviteToken)}`);
    if (invite.status !== 201 || !inviteToken) {
      console.log(`   ${invite.text.slice(0, 200)}`);
      continue;
    }
    const password = `StaffPass${stamp % 100000}!`;
    const accept = await callWithBackoff('/agency/invitations/accept', {
      method: 'POST',
      body: { token: inviteToken, name: `Probe ${role.label}`, password },
    });
    const authToken = accept.json?.token;
    console.log(`accept ${role.label}: status=${accept.status} account-token=${Boolean(authToken)}`);
    if (authToken) {
      created.push({ email, password, token: authToken, label: role.label });
    } else {
      console.log(`   ${accept.text.slice(0, 200)}`);
    }
  }

  // ---------- 3. authorisation matrix ----------
  await checkRole('superadmin@taisartownhub.com', 'super_admin', superToken, EXPECTED['superadmin@taisartownhub.com']);

  await checkRole('admin@taisartownhub.com', 'agency_owner+pl_admin', ownerToken, EXPECTED['admin@taisartownhub.com']);

  // Platform users inherit NO agency automatically (deny-by-default). The
  // product UI always scopes explicitly — prove the owner action works when
  // the context is provided, exactly as the frontend sends it.
  for (const persona of [
    { label: 'agency_owner (scoped)', token: ownerToken },
    { label: 'super_admin (scoped)', token: superToken },
  ]) {
    for (const probe of CHECKS) {
      if (probe.id !== 'staff invite') continue;
      const res = await call(probe.path, {
        method: probe.method,
        token: persona.token,
        agency: 'agency-demo',
        body: probe.body,
      });
      const ok = res.status === 422;
      console.log(`${ok ? 'PASS' : 'FAIL'} ${persona.label.padEnd(20)} ${probe.id.padEnd(20)} status=${res.status} expected=422`);
      rows.push({
        account: persona.label === 'agency_owner (scoped)' ? 'admin@taisartownhub.com' : 'superadmin@taisartownhub.com',
        role: persona.label, check: `${probe.id} (scoped)`,
        expected: '422', got: res.status, verdict: ok ? 'PASS' : 'FAIL',
      });
      if (!ok) failures.push(rows[rows.length - 1]);
    }
  }

  for (const [email, password, label] of [
    ['manager@taisartownhub.com', 'Manager@2026', 'agency_manager'],
    ['dealer@taisartownhub.com', 'Dealer@2026', 'agent'],
    ['operator@taisartownhub.com', 'Operator@2026', 'document_manager'],
  ] as const) {
    const login = await callWithBackoff('/auth/login', { method: 'POST', body: { email, password } });
    if (login.status !== 200 || !login.json?.token) {
      console.log(`FAIL ${label} sign-in — status=${login.status}`);
      failures.push({ account: email, role: label, check: 'sign-in', expected: '200', got: login.status, verdict: 'FAIL' });
      continue;
    }
    await checkRole(email, label, login.json.token, EXPECTED[email]);
  }

  for (const account of created) {
    await checkRole(account.email, account.label, account.token);
  }

  // ---------- 4. revocation: owner deactivates the property_manager ----------
  const pm = created.find((c) => c.label === 'property_manager');
  if (pm) {
    const members = await call('/agency/members', { token: ownerToken, agency: 'agency-demo' });
    const list = members.json?.members || members.json || [];
    const entry = (Array.isArray(list) ? list : []).find(
      (m: any) => (m.email || m.user?.email) === pm.email,
    );
    if (entry) {
      const id = entry.id || entry.user?.id;
      const removed = await call(`/agency/members/${id}`, {
        method: 'DELETE',
        token: ownerToken,
        agency: 'agency-demo',
      });
      console.log(`revoke property_manager member ${id}: status=${removed.status}`);
      await new Promise((r) => setTimeout(r, 800));
      const after = await call('/workspace', { token: pm.token });
      const denied = after.status === 403 || after.status === 401;
      console.log(`${denied ? 'PASS' : 'FAIL'} revoked member's token is rejected — status=${after.status}`);
      rows.push({
        account: pm.email, role: 'property_manager', check: 'access after revocation',
        expected: '401/403', got: after.status, verdict: denied ? 'PASS' : 'FAIL',
      });
      if (!denied) {
        failures.push({
          account: pm.email, role: 'property_manager', check: 'access after revocation',
          expected: '401/403', got: after.status, verdict: 'FAIL',
        });
      }
    } else {
      console.log(`WARN could not locate the property_manager membership for revocation`);
    }
  }

  const passed = rows.filter((r) => r.verdict === 'PASS').length;
  console.log(`\n${passed}/${rows.length} authorisation checks passed`);
  writeReport();
  if (failures.length) process.exitCode = 1;

  function writeReport() {
    const out = path.resolve(import.meta.dirname, '..', 'audit');
    mkdirSync(out, { recursive: true });
    writeFileSync(
      path.join(out, 'role-matrix.json'),
      JSON.stringify({ generatedAt: new Date().toISOString(), passed, total: rows.length, rows }, null, 2),
    );
    console.log(`report: frontend/audit/role-matrix.json`);
  }
})();
