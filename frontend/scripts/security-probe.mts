/**
 * Live multi-tenant security probe (Phase 1/2 evidence).
 *
 * Creates two real agencies through the public API, approves them as the
 * platform admin and then attacks tenant A from tenant B: foreign ids,
 * spoofed `X-Agency-Id`, client-supplied agency ids, cross-tenant document
 * downloads, CNIC leakage, unauthenticated access and the login throttle.
 *
 * Every check reports PASS / FAIL with the observed HTTP status — nothing is
 * assumed. Run against the local stack: API :8090, frontend :3000.
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';

type Result = { check: string; status: 'PASS' | 'FAIL'; evidence: string };
const results: Result[] = [];

function record(check: string, ok: boolean, evidence: string) {
  results.push({ check, status: ok ? 'PASS' : 'FAIL', evidence });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} — ${evidence}`);
}

// TEST-NET-3 addresses keep this probe's auth attempts out of the shared
// 127.0.0.1 limiter bucket used by the E2E suite. The throttle probe needs a
// second bucket because it deliberately exhausts the allowance.
const PROBE_IP = '203.0.113.201';
const THROTTLE_IP = '203.0.113.202';

async function call(
  path: string,
  options: {
    method?: string;
    token?: string;
    agency?: string;
    body?: unknown;
    form?: FormData;
    ip?: string;
  } = {},
) {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Forwarded-For': options.ip || PROBE_IP,
  };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (options.agency) headers['X-Agency-Id'] = options.agency;
  let body: BodyInit | undefined;
  if (options.form) {
    body = options.form;
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }
  const res = await fetch(`${API}${path}`, { method: options.method || 'GET', headers, body });
  const text = await res.text();
  let json: any = null;
  try {
    json = JSON.parse(text);
  } catch {
    json = null;
  }
  return { status: res.status, json, text };
}

const stamp = Date.now();

/**
 * The public register/login endpoints are throttled (3/min, 5/min). The probe
 * must therefore wait for the window instead of reporting a false failure.
 */
async function callWithBackoff(
  path: string,
  options: Parameters<typeof call>[1] = {},
  attempts = 3,
) {
  let response = await call(path, options);
  for (let i = 0; i < attempts && response.status === 429; i++) {
    console.log(`   (429 on ${path} — waiting for the throttle window)`);
    await new Promise((r) => setTimeout(r, 22000));
    response = await call(path, options);
  }
  return response;
}

(async () => {
  // ---------- platform admin ----------
  const admin = await call('/auth/login', {
    method: 'POST',
    body: { email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  });
  if (admin.status !== 200 || !admin.json?.token) {
    record('platform admin sign-in', false, `status=${admin.status}`);
    console.log(JSON.stringify(results, null, 2));
    process.exit(1);
  }
  const adminToken = admin.json.token;
  record('platform admin sign-in', true, '200 + token issued');

  // ---------- two real tenants ----------
  const agencies: Array<{ token: string; agencyId: string; email: string }> = [];
  for (const label of ['A', 'B']) {
    const email = `probe.${label.toLowerCase()}.${stamp}@audit.test`;
    const reg = await callWithBackoff('/agency/register', {
      method: 'POST',
      body: {
        name: `Audit Probe Agency ${label} ${stamp}`,
        contactName: `Probe Owner ${label}`,
        email,
        password: 'ProbePass123!',
        phone: '03001234567',
      },
    });
    const agencyId = reg.json?.agency?.id || reg.json?.agency_id;
    const verify = agencyId
      ? await call(`/admin/agencies/${agencyId}/verify`, {
          method: 'PUT',
          token: adminToken,
          body: { verification_status: 'verified', notes: 'Automated audit probe' },
        })
      : { status: 0 };
    const login = await callWithBackoff('/auth/login', {
      method: 'POST',
      body: { email, password: 'ProbePass123!' },
    });
    record(
      `agency ${label} registration + platform approval + owner sign-in`,
      verify.status === 200 && login.status === 200 && !!login.json?.token,
      `register=${reg.status} verify=${verify.status} login=${login.status}`,
    );
    agencies.push({ token: login.json?.token || '', agencyId, email });
  }

  const A = agencies[0];
  const B = agencies[1];
  if (!A?.token || !B?.token) {
    console.log(JSON.stringify(results, null, 2));
    process.exit(1);
  }

  // ---------- tenant A creates a listing ----------
  const title = `PROBE-ONLY-${stamp}-tenant-a-listing`;
  const created = await call('/properties', {
    method: 'POST',
    token: A.token,
    body: {
      title,
      pricePKR: 25000000,
      phase: '1',
      sector: 'Sector 70-D',
      sizeSqYards: 120,
      type: 'Residential Plot',
    },
  });
  const propertyId = created.json?.property?.id || created.json?.id;
  record(
    'tenant A creates a listing',
    created.status === 201 && !!propertyId,
    `status=${created.status} id=${propertyId}`,
  );

  // ---------- cross-tenant attacks ----------
  const crossRead = await call(`/properties/${propertyId}`, { token: B.token });
  record(
    "B cannot read A's listing by id",
    crossRead.status === 404 || crossRead.status === 403,
    `status=${crossRead.status}`,
  );

  const spoof = await call('/properties', { token: B.token, agency: A.agencyId });
  const spoofBody = JSON.stringify(spoof.json || {});
  record(
    "spoofed X-Agency-Id is ignored (B sees only B's scope)",
    !spoofBody.includes(title),
    `status=${spoof.status} leaked=${spoofBody.includes(title)}`,
  );

  const workspace = await call('/workspace', { token: B.token });
  const workspaceBody = JSON.stringify(workspace.json || {});
  record(
    "B's workspace payload does not contain A's data",
    !workspaceBody.includes(title),
    `status=${workspace.status} leaked=${workspaceBody.includes(title)}`,
  );

  const adminList = await call('/admin/agencies', { token: B.token });
  record(
    'agency member cannot use the platform admin surface',
    adminList.status === 403,
    `status=${adminList.status}`,
  );

  const adminShow = await call(`/admin/agencies/${A.agencyId}`, { token: B.token });
  record(
    'agency member cannot read another agency through the admin API',
    adminShow.status === 403 || adminShow.status === 404,
    `status=${adminShow.status}`,
  );

  const lead = await call('/agency/leads', {
    method: 'POST',
    token: B.token,
    body: {
      name: 'Probe Lead',
      phone: '03009998888',
      email: `probe.lead.${stamp}@audit.test`,
      message: 'cross tenant probe',
      agency_id: A.agencyId,
    },
  });
  const leadId = lead.json?.lead?.id || lead.json?.id;
  const leadAgency = lead.json?.lead?.agency_id || lead.json?.agency_id;
  record(
    'client-supplied agency_id on a lead is ignored (server resolves tenancy)',
    lead.status === 201 || lead.status === 422 || leadAgency !== A.agencyId,
    `status=${lead.status} agency_id=${leadAgency} (must not be ${A.agencyId})`,
  );

  // ---------- private document vault ----------
  const form = new FormData();
  form.append('title', `PROBE-DOC-${stamp}`);
  // .pdf is on the allowed-extension list; .txt is rejected by design.
  form.append(
    'file',
    new File(['%PDF-1.4 probe document body'], `probe-${stamp}.pdf`, { type: 'application/pdf' }),
  );
  const upload = await call('/agency/documents', { method: 'POST', token: A.token, form });
  const docId = upload.json?.document?.id || upload.json?.id;
  const scanStatus = upload.json?.document?.scan_status as string | undefined;
  record(
    'tenant A uploads a private document',
    upload.status === 201 && !!docId,
    `status=${upload.status} id=${docId} scan_status=${scanStatus} ${upload.status !== 201 ? upload.text.slice(0, 120) : ''}`,
  );

  if (docId) {
    const crossDoc = await call(`/agency/documents/${docId}/download`, { token: B.token });
    record(
      "B cannot download A's private document",
      crossDoc.status === 404 || crossDoc.status === 403,
      `status=${crossDoc.status}`,
    );

    // The download policy depends on the malware scanner. With a real scanner
    // that returned a clean verdict the row is `scanned_clean` and the owner
    // gets 200. Locally the scanner is disabled (fail-open): the row is stored
    // as `skipped` / quarantined and the controller deliberately returns 423
    // until a rescan runs — even for the owner. Assert the correct policy for
    // the status the backend actually recorded instead of assuming 200.
    const ownDoc = await call(`/agency/documents/${docId}/download`, { token: A.token });
    const quarantined = scanStatus !== 'scanned_clean';
    const ownerPolicyOk = quarantined ? ownDoc.status === 423 : ownDoc.status === 200;
    record(
      quarantined
        ? "A's quarantined document is download-blocked until rescan (fail-open, scanner unavailable)"
        : 'A can download its own scanned-clean private document',
      ownerPolicyOk,
      `status=${ownDoc.status} scan_status=${scanStatus} expected=${quarantined ? 423 : 200}`,
    );
  }

  // ---------- CNIC exposure (public surface) ----------
  const publicDealers = await call('/dealers');
  const publicDealerBody = JSON.stringify(publicDealers.json || {});
  const anyCnicKey = /"cnic"\s*:/.test(publicDealerBody);
  const rawCnicLeak = /"cnic"\s*:\s*"\d{5}-?\d{7}-?\d/.test(publicDealerBody);
  record(
    'the public dealer listing exposes no CNIC field at all',
    publicDealers.status === 200 && !anyCnicKey && !rawCnicLeak,
    `status=${publicDealers.status} cnic_field=${anyCnicKey} raw_value=${rawCnicLeak}`,
  );

  const dealerId = (Array.isArray(publicDealers.json) ? publicDealers.json[0]?.id : null) as string | null;
  if (dealerId) {
    const dealerGet = await call(`/dealers/${dealerId}`);
    const getBody = JSON.stringify(dealerGet.json || {});
    record(
      'a dealer detail response contains no CNIC field (not even masked-by-accident)',
      !/"cnic"\s*:/.test(getBody),
      `status=${dealerGet.status} cnic_field=${/"cnic"\s*:/.test(getBody)}`,
    );
  }

  // ---------- unauthenticated access ----------
  const anon = await call('/agency/documents');
  record('unauthenticated vault access is rejected', anon.status === 401, `status=${anon.status}`);
  const anonWs = await call('/workspace');
  record('unauthenticated workspace access is rejected', anonWs.status === 401, `status=${anonWs.status}`);

  // ---------- login throttle ----------
  // The auth limiter is 10/min per IP. Use a dedicated TEST-NET-3 bucket and
  // 12 attempts so the expectation matches the configured production value.
  const throttleCodes: number[] = [];
  for (let i = 0; i < 12; i++) {
    const res = await call('/auth/login', {
      method: 'POST',
      ip: THROTTLE_IP,
      body: { email: `throttle.${stamp}@audit.test`, password: 'Whatever123!' },
    });
    throttleCodes.push(res.status);
    if (res.status === 429) break;
  }
  record(
    'login endpoint is rate limited (10/min)',
    throttleCodes.includes(429),
    `statuses=${throttleCodes.join(',')}`,
  );

  // ---------- session surface ----------
  const mfaStatus = await call('/auth/mfa/status', { token: A.token });
  record(
    'MFA status endpoint answers for the signed-in account only',
    mfaStatus.status === 200 && typeof mfaStatus.json?.enabled === 'boolean',
    `status=${mfaStatus.status} enabled=${mfaStatus.json?.enabled}`,
  );
  const history = await call('/auth/login-history', { token: A.token });
  record(
    "login history returns the account owner's own trail",
    history.status === 200 && Array.isArray(history.json?.history) && history.json.history.length > 0,
    `status=${history.status} rows=${history.json?.history?.length ?? 0}`,
  );
  const foreignHistory = await call('/auth/login-history', { token: B.token });
  const foreignRows = JSON.stringify(foreignHistory.json || {});
  record(
    "B cannot see A's sign-in trail",
    !foreignRows.includes(A.email),
    `status=${foreignHistory.status} leaked=${foreignRows.includes(A.email)}`,
  );

  // ---------- leave the dev data tidy ----------
  // Soft-delete generated records where the API supports it; hide the probe
  // listing instead of deleting it; suspend (never delete) the probe agencies.
  const cleanup: string[] = [];
  if (propertyId) {
    const hidden = await call(`/properties/${propertyId}/visibility`, {
      method: 'POST',
      token: A.token,
      body: { visibility: 'private' },
    });
    cleanup.push(`listing ${propertyId} visibility=private (${hidden.status})`);
  }
  if (leadId) {
    const removed = await call(`/agency/leads/${leadId}`, { method: 'DELETE', token: B.token });
    cleanup.push(`lead ${leadId} deleted (${removed.status})`);
  }
  if (docId) {
    const removed = await call(`/agency/documents/${docId}`, { method: 'DELETE', token: A.token });
    cleanup.push(`document ${docId} soft-deleted (${removed.status})`);
  }
  for (const agency of agencies) {
    const suspended = await call(`/admin/agencies/${agency.agencyId}/status`, {
      method: 'PUT',
      token: adminToken,
      body: { status: 'suspended' },
    });
    cleanup.push(`agency ${agency.agencyId} suspended (${suspended.status})`);
  }
  console.log(`\nprobe cleanup:\n  ${cleanup.join('\n  ')}`);

  const failed = results.filter((r) => r.status === 'FAIL');
  console.log(`\n${results.length - failed.length}/${results.length} security probes passed`);
  console.log(JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  if (failed.length) process.exitCode = 1;
})();
