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

test('AuthPage imports useEffect (used by CNIC masking effect)', () => {
  const source = read('./AuthPage.tsx');
  assert.ok(
    /import React,\s*\{[^}]*useEffect[^}]*\}\s*from\s*'react'/.test(source),
    'useEffect must be imported from react'
  );
  assert.ok(source.includes("import { maskCNIC } from '../../utils/logic'"), 'maskCNIC import missing');
});

test('AuthPage CNIC masking effect references maskCNIC', () => {
  const source = read('./AuthPage.tsx');
  assert.ok(source.includes('setRegCnicMasked(maskCNIC(regCnic))'), 'CNIC masking effect body missing');
});