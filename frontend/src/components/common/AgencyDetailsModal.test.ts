import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the AgencyDetailsModal hooks-order fix.
 *
 * The modal previously called `useEffect` AFTER an early `return null;`,
 * which violated the Rules of Hooks and crashed the whole app with
 * "Rendered more hooks than during the previous render." when a dealer's
 * "Inspect Available Files" button was clicked.
 *
 * All hooks (useState, useEffect) must be declared before any early return.
 */
test('AgencyDetailsModal declares all hooks before any early return', () => {
  const source = read('./AgencyDetailsModal.tsx');
  const lines = source.split('\n');
  // Find the first early-return statement (the `if (!isOpen || !dealer) return null;`)
  const earlyReturnIdx = lines.findIndex((l) => /if \(!isOpen \|\| !dealer\) return null;/.test(l));
  assert.ok(earlyReturnIdx >= 0, 'early-return guard not found');
  // Every useState/useEffect/useRef/useMemo/useCallback must appear BEFORE that line.
  const hookLines = lines
    .map((l, i) => ({ l, i }))
    .filter(({ l }) => /\buse(State|Effect|Ref|Memo|Callback|Reducer)\b/.test(l));
  for (const { i } of hookLines) {
    assert.ok(
      i < earlyReturnIdx,
      `hook on line ${i + 1} appears AFTER the early return on line ${earlyReturnIdx + 1}`
    );
  }
  assert.ok(hookLines.length >= 2, 'expected at least useState + useEffect hooks');
});

test('AgencyDetailsModal has an Escape-key handler and body-scroll lock', () => {
  const source = read('./AgencyDetailsModal.tsx');
  assert.ok(source.includes("e.key === 'Escape'"), 'Escape-key handler missing');
  assert.ok(source.includes("document.body.style.overflow"), 'body-scroll lock missing');
});