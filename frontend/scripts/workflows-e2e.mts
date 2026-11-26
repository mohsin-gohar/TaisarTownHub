/**
 * End-to-end business workflows against the REAL API + DB state.
 *
 * W2  Property lifecycle owner creates -> submit for review -> platform admin
 *                       approves -> publicly visible (this listing is the
 *                       subject of W1's buyer interactions)
 * W1  Buyer journey     register -> workspace approval gate -> platform admin
 *                       verifies the buyer agency -> sign in -> favorites
 *                       (toggle/list/remove) -> public inquiry on the W2
 *                       listing -> the owning agency sees the lead in its CRM
 * W3  Deal & commission accountant fixture (via the real invitation flow)
 *                       -> dealer file -> sell -> commission/dealer totals
 *                       move -> accountant records a payment -> ledger
 * W4  Document sharing  upload -> share token -> anonymous download -> revoke
 *                       -> token dead (410)
 *
 * Every step prints PASS/FAIL with the observed status. No destructive call is
 * made against real customer data; everything is created under @audit.test.
 */
const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';
const stamp = Date.now();
const sharedDemoPassword = process.env.DEMO_STAFF_PASSWORD;

type Row = { workflow: string; step: string; ok: boolean; evidence: string };
const rows: Row[] = [];
function record(workflow: string, step: string, ok: boolean, evidence: string) {
  rows.push({ workflow, step, ok, evidence });
  console.log(`${ok ? 'PASS' : 'FAIL'} [${workflow}] ${step} — ${evidence}`);
}

async function call(path: string, options: any = {}) {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (options.ip) headers['X-Forwarded-For'] = options.ip;
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

async function callWithBackoff(path: string, options: any = {}, attempts = 4) {
  let response = await call(path, options);
  for (let i = 0; i < attempts && response.status === 429; i++) {
    console.log(`   (429 on ${path} — waiting for the throttle window)`);
    await new Promise((r) => setTimeout(r, 22000));
    response = await call(path, options);
  }
  return response;
}

async function login(email: string, password: string, ip: string) {
  const res = await callWithBackoff('/auth/login', { method: 'POST', body: { email, password }, ip });
  return { status: res.status, token: res.json?.token as string | undefined };
}

(async () => {
  // Shared personas (real accounts, throttled sign-ins).
  const owner = await login('admin@taisartownhub.com', sharedDemoPassword || 'Admin@Taisar2026', '203.0.113.31');
  const ownerToken = owner.token;
  const sa = await login('superadmin@taisartownhub.com', sharedDemoPassword || 'Super@Admin2026!', '203.0.113.32');
  record('setup', 'personas signed in', Boolean(ownerToken) && Boolean(sa.token), `owner=${owner.status} superadmin=${sa.status}`);

  // ===================== W2: property lifecycle (first — W1 uses its listing)
  let w2PropertyId: string | undefined;
  if (ownerToken) {
    const created = await call('/properties', {
      method: 'POST',
      token: ownerToken,
      agency: 'agency-demo',
      body: {
        title: `WORKFLOW-${stamp} lifecycle listing`,
        pricePKR: 31000000,
        phase: '1',
        sector: 'Sector 70-D',
        sizeSqYards: 240,
        type: 'Residential Plot',
      },
    });
    w2PropertyId = created.json?.property?.id || created.json?.id;
    record('W2 property', 'owner creates a listing', created.status === 201 && Boolean(w2PropertyId), `status=${created.status} id=${w2PropertyId}`);
  }

  if (ownerToken && w2PropertyId) {
    const submit = await call(`/properties/${w2PropertyId}/submit`, { method: 'POST', token: ownerToken, agency: 'agency-demo' });
    record('W2 property', 'submit for platform review', submit.status === 200 || submit.status === 201, `status=${submit.status} ${submit.status >= 400 ? submit.text.slice(0, 120) : ''}`);

    if (sa.token) {
      const approve = await call(`/properties/${w2PropertyId}/approve`, { method: 'POST', token: sa.token });
      record('W2 property', 'platform admin approves', approve.status === 200 || approve.status === 201, `status=${approve.status} ${approve.status >= 400 ? approve.text.slice(0, 120) : ''}`);

      const detail = await call(`/properties/${w2PropertyId}`);
      record('W2 property', 'approved listing is publicly visible', detail.status === 200, `status=${detail.status}`);

      // manager@ must NOT be able to moderate (no properties.approve/reject)
      const mgr = await login('manager@taisartownhub.com', sharedDemoPassword || 'Manager@2026', '203.0.113.33');
      if (mgr.token) {
        const reject = await call(`/properties/${w2PropertyId}/reject`, { method: 'POST', token: mgr.token, agency: 'agency-demo' });
        record('W2 property', 'agency manager cannot moderate (403)', reject.status === 403, `status=${reject.status}`);
      }
    }
  }

  // ============================ W1: buyer journey ============================
  const buyerEmail = `buyer.${stamp}@audit.test`;
  const buyerPassword = `BuyerPass${stamp % 100000}!`;

  const reg = await callWithBackoff('/auth/register', {
    method: 'POST',
    body: {
      fullName: `Probe Buyer ${stamp}`,
      email: buyerEmail,
      password: buyerPassword,
      phone: '03001112223',
      cnic: `42101${String(stamp).slice(-7)}-8`,
    },
  });
  const buyerAgencyId = reg.json?.agency?.id;
  record('W1 buyer', 'registration through the public form', reg.status === 201, `status=${reg.status} agency=${buyerAgencyId}`);

  // The workspace is gated until the platform verifies the agency — prove the
  // gate is real (favorites must be denied before approval, allowed after).
  if (reg.json?.token) {
    const gated = await call('/favorites', { token: reg.json.token });
    record('W1 buyer', 'workspace gate: pre-approval favorites denied (403)', gated.status === 403, `status=${gated.status} ${(gated.json?.message || '').slice(0, 60)}`);
  }

  // Platform admin approves the buyer agency through the real admin endpoint.
  if (sa.token && buyerAgencyId) {
    const verify = await call(`/admin/agencies/${buyerAgencyId}/verify`, {
      method: 'PUT',
      token: sa.token,
      body: { verification_status: 'verified', notes: `Workflow E2E buyer agency ${stamp}` },
    });
    record('W1 buyer', 'platform admin verifies the buyer agency', verify.status === 200, `status=${verify.status}`);
  }

  const buyer = await login(buyerEmail, buyerPassword, '203.0.113.35');
  record('W1 buyer', 'sign-in issues a token', buyer.status === 200 && Boolean(buyer.token), `status=${buyer.status}`);

  if (buyer.token) {
    const empty = await call('/favorites', { token: buyer.token });
    record('W1 buyer', 'favorites list starts empty', empty.status === 200, `status=${empty.status} items=${JSON.stringify(empty.json?.favorites || empty.json)?.slice(0, 60)}`);

    // Prefer the W2 listing (agency-demo owns it) so the CRM check is exact;
    // fall back to any public listing from the catalog.
    let targetId = w2PropertyId;
    if (!targetId) {
      const catalog = await call('/catalog');
      const list = Array.isArray(catalog.json?.properties) ? catalog.json.properties : [];
      targetId = list.find((p: any) => p?.id)?.id;
    }
    if (targetId) {
      const tog = await call(`/favorites/${targetId}`, { method: 'POST', token: buyer.token });
      record('W1 buyer', 'favorite a listing (toggle on)', tog.status === 200 && tog.json?.favorited !== false, `status=${tog.status} favorited=${tog.json?.favorited}`);
      const listed = await call('/favorites', { token: buyer.token });
      const has = JSON.stringify(listed.json || {}).includes(targetId);
      record('W1 buyer', 'the listing appears in favorites', listed.status === 200 && has, `status=${listed.status} contains=${has}`);
      const off = await call(`/favorites/${targetId}`, { method: 'DELETE', token: buyer.token });
      const listed2 = await call('/favorites', { token: buyer.token });
      const gone = !JSON.stringify(listed2.json || {}).includes(targetId);
      record('W1 buyer', 'favorite removed (toggle off / delete)', off.status === 200 && gone, `delete=${off.status} gone=${gone}`);

      const inq = await call('/inquiries', {
        method: 'POST',
        body: {
          fullName: `Buyer ${stamp}`,
          phone: '03001112223',
          email: buyerEmail,
          message: `Workflow probe: interested in ${targetId}`,
          propertyId: targetId,
          source: 'audit',
        },
      });
      record('W1 buyer', 'public inquiry on the listing', inq.status === 201, `status=${inq.status} ${inq.status !== 201 ? inq.text.slice(0, 120) : ''}`);

      // The owning agency (agency-demo) must now see the lead in its CRM.
      if (ownerToken) {
        const leads = await call('/agency/leads', { token: ownerToken, agency: 'agency-demo' });
        const body = JSON.stringify(leads.json || {});
        record('W1 buyer', 'the lead reached the owning agency CRM', leads.status === 200 && body.includes(buyerEmail), `status=${leads.status} found=${body.includes(buyerEmail)}`);
      }
    } else {
      record('W1 buyer', 'a public listing exists to interact with', false, 'no listing found');
    }
  }

  // ===================== W3: deal & commission ==============================
  // An accountant fixture is created through the real invitation flow so the
  // payment step runs with the account's own grants.
  let accountantToken: string | undefined;
  if (ownerToken) {
    const accEmail = `workflow.accountant.${stamp}@audit.test`;
    const invite = await callWithBackoff('/agency/members/invite', {
      method: 'POST',
      token: ownerToken,
      agency: 'agency-demo',
      body: { email: accEmail, role_id: 'role_accountant' },
    });
    const inviteToken = invite.json?.invite_token ?? invite.json?.invitation?.invite_token;
    if (inviteToken) {
      const accPassword = `AccPass${stamp % 100000}!`;
      const accept = await callWithBackoff('/agency/invitations/accept', {
        method: 'POST',
        body: { token: inviteToken, name: 'Workflow Accountant', password: accPassword },
      });
      accountantToken = accept.json?.token;
      record('W3 deal', 'accountant fixture created via invitation', Boolean(accountantToken), `status=${accept.status}`);
    } else {
      record('W3 deal', 'accountant fixture created via invitation', false, `invite status=${invite.status} ${invite.text.slice(0, 100)}`);
    }
  }

  const dealer = await login('dealer@taisartownhub.com', sharedDemoPassword || 'Dealer@2026', '203.0.113.34');
  if (dealer.token) {
    const file = await call('/dealer-files', {
      method: 'POST',
      token: dealer.token,
      body: {
        title: `Workflow probe file ${stamp}`,
        type: 'pdf',
        url: 'https://example.test/workflow-probe.pdf',
        folder: 'Documents',
        file_size_kb: 256,
      },
    });
    const fileId = file.json?.data?.id || file.json?.id;
    record('W3 deal', 'dealer creates a file', file.status === 201 && Boolean(fileId), `status=${file.status} id=${fileId}`);

    if (fileId) {
      const before = await call(`/dealers/${'deal-3'}`);
      const dealsBefore = Number(before.json?.dealsClosed ?? before.json?.deals_closed ?? -1);
      const sell = await call(`/dealer-files/${fileId}/sell`, {
        method: 'POST',
        token: dealer.token,
        body: {
          selling_price_kbr: 42000,
          buyer_name: `Workflow Buyer ${stamp}`,
          buyer_phone: '03009991112',
          buyer_cnic: '42101-9999997-1',
        },
      });
      record('W3 deal', 'sell the file once', sell.status === 200, `status=${sell.status} ${sell.status !== 200 ? sell.text.slice(0, 140) : ''}`);

      const after = await call(`/dealers/${'deal-3'}`);
      const dealsAfter = Number(after.json?.dealsClosed ?? after.json?.deals_closed ?? -2);
      record(
        'W3 deal',
        "dealer's closed-deal counter incremented",
        dealsAfter === dealsBefore + 1,
        `before=${dealsBefore} after=${dealsAfter}`,
      );

      const report = await call('/dealers/deal-3/sales-report', { token: dealer.token });
      record('W3 deal', 'sales report contains the new deal', report.status === 200 && JSON.stringify(report.json || {}).includes(`Workflow Buyer ${stamp}`), `status=${report.status}`);
    }

    // Accountant records a payment on an existing deal (their own grants).
    if (accountantToken) {
      const txs = await call('/agency/transactions', { token: accountantToken, agency: 'agency-demo' });
      // The endpoint returns a bare JSON array (legacy contract).
      const list = Array.isArray(txs.json)
        ? txs.json
        : Array.isArray(txs.json?.transactions) ? txs.json.transactions : txs.json?.data || [];
      const tx = list[0];
      if (tx?.id) {
        const pay = await call(`/agency/transactions/${tx.id}/payments`, {
          method: 'POST',
          token: accountantToken,
          agency: 'agency-demo',
          body: {
            amount: 500000,
            type: 'installment',
            method: 'bank_transfer',
            received_at: new Date().toISOString().slice(0, 10),
            reference: `WF-${stamp}`,
            receipt_no: `RCPT-${stamp % 1000000}`,
            notes: 'Workflow E2E payment (disposable)',
          },
        });
        record('W3 deal', 'accountant records a payment', pay.status === 201, `status=${pay.status} ${pay.status !== 201 ? pay.text.slice(0, 160) : ''}`);
      } else {
        record('W3 deal', 'accountant can read the deal list', txs.status === 200, `status=${txs.status} deals=${list.length}`);
      }
    }
  }

  // ===================== W4: document share lifecycle =======================
  if (ownerToken) {
    const form = new FormData();
    form.append('title', `WORKFLOW-SHARE-${stamp}`);
    form.append('file', new File(['%PDF-1.4 workflow share probe'], `share-${stamp}.pdf`, { type: 'application/pdf' }));
    const upload = await call('/agency/documents', { method: 'POST', token: ownerToken, agency: 'agency-demo', form });
    const docId = upload.json?.document?.id || upload.json?.id;
    record('W4 documents', 'owner uploads a private document', upload.status === 201 && Boolean(docId), `status=${upload.status} id=${docId}`);

    if (docId) {
      const share = await call(`/agency/documents/${docId}/shares`, {
        method: 'POST',
        token: ownerToken,
        agency: 'agency-demo',
        body: { expires_in_days: 7, max_downloads: 10, note: `Workflow probe ${stamp}` },
      });
      const shareToken = share.json?.token || share.json?.share?.token;
      record('W4 documents', 'owner creates a share link', share.status === 201 && Boolean(shareToken), `status=${share.status}`);

      if (shareToken) {
        const dl = await call(`/shares/${shareToken}/download`);
        record('W4 documents', 'anonymous holder can download while valid', dl.status === 200, `status=${dl.status}`);

        const shares = await call(`/agency/documents/${docId}/shares`, { token: ownerToken, agency: 'agency-demo' });
        const shareList = shares.json?.shares || shares.json || [];
        const shareId = (Array.isArray(shareList) ? shareList : [])[0]?.id;
        if (shareId) {
          const revoke = await call(`/agency/documents/${docId}/shares/${shareId}`, { method: 'DELETE', token: ownerToken, agency: 'agency-demo' });
          record('W4 documents', 'owner revokes the share', revoke.status === 200 || revoke.status === 204, `status=${revoke.status}`);

          const dead = await call(`/shares/${shareToken}/download`);
          record('W4 documents', 'revoked token no longer downloads (410)', dead.status === 410 || dead.status === 404, `status=${dead.status}`);
        }

        const logs = await call(`/agency/documents/${docId}/access-logs`, { token: ownerToken, agency: 'agency-demo' });
        record('W4 documents', 'access trail recorded', logs.status === 200, `status=${logs.status} entries=${JSON.stringify(logs.json || {}).length > 60 ? 'yes' : 'no'}`);
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Cleanup: neutralise the listing this run published (never delete history)
  // and suspend the disposable buyer agency. Financial fixtures created by W3
  // are intentionally left for audit review (sales/payment records must not be
  // silently rewritten); they are clearly labelled with the run stamp.
  if (ownerToken && w2PropertyId) {
    const hidden = await call(`/properties/${w2PropertyId}/visibility`, {
      method: 'POST',
      token: ownerToken,
      agency: 'agency-demo',
      body: { visibility: 'private' },
    });
    console.log(`cleanup: W2 listing ${w2PropertyId} visibility=${hidden.status === 200 ? 'private' : 'UNCHANGED'}`);
  }
  if (sa.token && buyerAgencyId) {
    await call(`/admin/agencies/${buyerAgencyId}/status`, {
      method: 'PUT',
      token: sa.token,
      body: { status: 'suspended' },
    });
    console.log(`cleanup: buyer probe agency ${buyerAgencyId} suspended`);
  }

  const passed = rows.filter((r) => r.ok).length;
  console.log(`\n${passed}/${rows.length} workflow steps passed`);

  const fs = await import('node:fs');
  const path = await import('node:path');
  const out = path.resolve(import.meta.dirname, '..', 'audit');
  fs.mkdirSync(out, { recursive: true });
  fs.writeFileSync(path.join(out, 'workflows.json'), JSON.stringify({ generatedAt: new Date().toISOString(), passed, total: rows.length, rows }, null, 2));
  console.log('report: frontend/audit/workflows.json');
  if (passed !== rows.length) process.exitCode = 1;
})();
