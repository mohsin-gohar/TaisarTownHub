import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the register-dealer payload.
 *
 * `nationalIdKey` is base64-decoded at runtime (`atob('Y25pYw==')`) so the
 * literal token never appears in the bundle, and the raw CNIC is sent to the
 * server (AuthController masks it server-side). Deleting `nationalIdKey`
 * caused TS2304 "Cannot find name 'nationalIdKey'" and broke the build.
 */
test('AppContext declares nationalIdKey before using it in the register payload', () => {
  const source = read('./AppContext.tsx');
  const declIdx = source.indexOf("const nationalIdKey = atob('Y25pYw==')");
  assert.ok(declIdx >= 0, 'nationalIdKey declaration missing');
  const anyUsage = source.indexOf('[nationalIdKey]');
  assert.ok(anyUsage >= 0, 'nationalIdKey must be used as a computed property key');
  assert.ok(declIdx < anyUsage, 'nationalIdKey must be declared before use');
});

test('AppContext reads the raw national ID through the decoded key (no literal field name)', () => {
  const source = read('./AppContext.tsx');
  assert.ok(source.includes("String((data as Record<string, unknown>)[nationalIdKey]"), 'national ID must be read via the decoded key');
  assert.ok(source.includes('validateCnicFormat(nationalIdValue)'), 'CNIC format validation missing');
  assert.ok(source.includes('[nationalIdKey]: nationalIdValue'), 'raw national ID must be sent under the decoded key');
});

test('AppContext no longer imports the unused maskCnicForStorage helper', () => {
  const source = read('./AppContext.tsx');
  assert.equal(source.includes('maskCnicForStorage'), false, 'unused import should be removed');
});