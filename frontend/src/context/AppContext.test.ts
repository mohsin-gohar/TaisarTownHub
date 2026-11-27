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

test('CMS settings persist in demo mode and sync through the authenticated workspace', () => {
  const source = read('./AppContext.tsx');
  assert.match(source, /localStorage\\.setItem\\(.*STORAGE_KEY_PREFIX.*cms/);
  assert.match(source, /cms: cmsConfig/);
  assert.match(source, /Saving to MySQL/);
  assert.doesNotMatch(source, /until a backend CMS endpoint is implemented/);
});
