import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the AuthPage crash ("Unexpected token '{'").
 *
 * The `import { maskCNIC }` statement was illegally placed inside the
 * component body (after the function signature), which is a SyntaxError.
 * All imports must live at the top of the module, before any component
 * definition.
 */
test('AuthPage keeps all imports at module top', () => {
  const source = read('./AuthPage.tsx');
  const lines = source.split('\n');
  // Every `import` statement must occur before the first `export const` / function component.
  const firstExportIdx = lines.findIndex((l) => /^\s*export\s+(const|function|default)/.test(l));
  assert.ok(firstExportIdx >= 0, 'component export not found');
  const illegalImports = lines
    .map((l, i) => ({ l, i }))
    .filter(({ l, i }) => /^\s*import\s/.test(l) && i > firstExportIdx);
  assert.equal(
    illegalImports.length,
    0,
    `import statements found after component export: ${illegalImports.map((x) => x.i + 1).join(', ')}`
  );
});

test('AuthPage imports useEffect (used by the initial-tab sync effect)', () => {
  const source = read('./AuthPage.tsx');
  assert.ok(
    /import React,\s*\{[^}]*useEffect[^}]*\}\s*from\s*'react'/.test(source),
    'useEffect must be imported from react'
  );
});

test('AuthPage registration collects the same fields the backend accepts and never asks for CNIC', () => {
  const source = read('./AuthPage.tsx');
  // Self-registration no longer retains CNIC (backend AuthController::register
  // validates `cnic` only on the legacy route and discards it). The public
  // form must not re-introduce a CNIC field.
  assert.ok(source.includes('registerDealerAccount'), 'registerDealerAccount usage missing');
  for (const field of ['regFullName', 'regEmail', 'regPhone', 'regAgency', 'regPassword']) {
    assert.ok(source.includes(field), `registration field ${field} missing`);
  }
  assert.ok(
    !/regCnic|formatCnicInput|NADRA CNIC/.test(source),
    'CNIC must not be collected by public self-registration'
  );
});