import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the register-dealer payload.
 *
 * Self-registration no longer collects or transmits a national ID: the
 * backend (`AgencyController::register` via `AuthController::register`) no
 * longer retains CNIC, and the public form follows the same contract. These
 * guards ensure a raw CNIC can never silently re-enter the register payload
 * (which would ship unmasked national IDs into the public bundle).
 */
test('AppContext register payload never carries a raw national ID field', () => {
  const source = read('./AppContext.tsx');
  assert.equal(
    /nationalIdKey|atob\('Y25pYw'/.test(source),
    false,
    'decoded national-ID key must not reappear in the register flow'
  );
  assert.equal(
    /\bcnic\b/i.test(source),
    false,
    'raw CNIC must not be sent by registerDealerAccount (backend discards it)'
  );
});

test('AppContext registers through the agency registration endpoint (no legacy national-ID payload)', () => {
  const source = read('./AppContext.tsx');
  assert.ok(
    source.includes('agencyOrBranch'),
    'registerDealerAccount must pass the agency/branch name'
  );
  assert.ok(
    !/\[nationalIdKey\]/.test(source),
    'computed national-ID key must be absent from the register payload'
  );
});

test('AppContext no longer imports the unused maskCnicForStorage helper', () => {
  const source = read('./AppContext.tsx');
  assert.equal(source.includes('maskCnicForStorage'), false, 'unused import should be removed');
});

test('CMS changes are reported saved only after the backend acknowledges the CMS collection write', () => {
  const context = read('./AppContext.tsx');
  const api = read('../services/api.ts');
  assert.ok(api.includes("collections: { cms: config }"));
  assert.ok(api.includes("body?.written?.cms === 1"));
  assert.ok(context.includes('const saved = await api.saveCmsConfig(nextConfig)'));
  assert.ok(context.includes("showToast('success', 'Website content saved and published.')"));
  assert.equal(context.includes('changes persist only in this session until a backend CMS endpoint is implemented'), false);
});

test('production workspace mutations are blocked when the API is unavailable', () => {
  const source = read('./AppContext.tsx');
  assert.ok(source.includes('if (!IS_DEMO && !backendConnected)'));
  assert.ok(source.includes('This change was not saved'));
});

test('inquiry status and notes keep server state on failed writes', () => {
  const source = read('./AppContext.tsx');
  assert.ok(source.includes('const previous = inquiries.find((inquiry) => inquiry.id === id)'));
  assert.ok(source.includes("setInquiries((prev) => prev.map((inquiry) => (inquiry.id === id ? { ...inquiry, status } : inquiry)))"));
  assert.ok(source.includes("setInquiries((prev) => prev.map((inquiry) => (inquiry.id === id ? { ...inquiry, adminNotes: notes } : inquiry)))"));
  assert.equal(source.includes("status: 'Pending'"), false);
  assert.equal(source.includes("adminNotes: ''"), false);
});
