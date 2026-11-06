import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guard for the launch UI.
 *
 * The public navbar and the admin header must not expose internal
 * infrastructure labels ("MySQL Live") or demo-build badges ("DEMO MODE").
 * These made the finished product look like a staging environment.
 */
const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('public navbar does not show internal/demo status badges', () => {
  const source = read('./Navbar.tsx');
  assert.equal(source.includes('MySQL Live'), false);
  assert.equal(source.includes('DEMO MODE'), false);
  assert.equal(source.includes('backendConnected'), false);
});

test('admin header does not show internal/demo status badges', () => {
  const source = read('../admin/AdminLayout.tsx');
  assert.equal(source.includes('MySQL Live'), false);
  assert.equal(source.includes('DEMO MODE'), false);
  assert.equal(source.includes('Exit to Live Site'), false);
  assert.equal(source.includes('View Public Site'), true);
});

test('footer does not describe the product as a demo', () => {
  const source = read('./Footer.tsx');
  assert.equal(source.includes('Real Estate Demo & Agency Platform'), false);
  assert.equal(source.includes('Stored securely in our MySQL database'), false);
});
