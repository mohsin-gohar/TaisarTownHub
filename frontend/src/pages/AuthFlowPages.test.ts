import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('password reset and email verification remove one-time tokens from browser history', () => {
  const source = read('./AuthFlowPages.tsx');
  assert.equal((source.match(/window\.history\.replaceState/g) || []).length, 2);
});

test('email verification sends the token in a POST body, not the API URL', () => {
  const source = read('./AuthFlowPages.tsx');
  assert.equal(source.includes("method: 'POST'"), true);
  assert.equal(source.includes('body: JSON.stringify({ email, token })'), true);
  assert.equal(source.includes('/auth/email/verify?email='), false);
});
