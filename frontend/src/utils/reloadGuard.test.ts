/**
 * Reload rate guard — regression tests for the sliding-window limiter that
 * protects the demo environment from rapid refresh loops.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkReloadLimit, RELOAD_WINDOW_MS, MAX_RELOADS_PER_WINDOW, type TimestampStore } from './reloadGuard';

/** In-memory sessionStorage mock. */
function mockStore(): TimestampStore & { dump(): string } {
  let data: string | null = null;
  return {
    getItem: (k) => (k === 'tth_reload_guard' ? data : null),
    setItem: (k, v) => { if (k === 'tth_reload_guard') data = v; },
    dump: () => data ?? '',
  };
}

test('first visit is never blocked', () => {
  const store = mockStore();
  const r = checkReloadLimit(store, 1_000_000);
  assert.equal(r.blocked, false);
  assert.equal(r.reloadCount, 0);
});

test('reloads within the limit stay allowed', () => {
  const store = mockStore();
  const base = 1_000_000;
  checkReloadLimit(store, base); // first visit (no reload counted)
  for (let i = 1; i <= MAX_RELOADS_PER_WINDOW; i++) {
    const r = checkReloadLimit(store, base + i * 1000);
    assert.equal(r.blocked, false, `reload ${i} should be allowed`);
  }
});

test('reloads beyond the limit are blocked with a retry countdown', () => {
  const store = mockStore();
  const base = 2_000_000;
  checkReloadLimit(store, base);
  let last = { blocked: false, retryAfterSec: 0 };
  for (let i = 1; i <= MAX_RELOADS_PER_WINDOW + 2; i++) {
    last = checkReloadLimit(store, base + i * 1000);
  }
  assert.equal(last.blocked, true, '7th reload inside the window must be blocked');
  assert.ok(last.retryAfterSec >= 1 && last.retryAfterSec <= 60);
});

test('old timestamps slide out of the window (no permanent ban)', () => {
  const store = mockStore();
  const base = 3_000_000;
  checkReloadLimit(store, base);
  for (let i = 1; i <= MAX_RELOADS_PER_WINDOW + 1; i++) {
    checkReloadLimit(store, base + i * 1000); // trip the limit
  }
  // After the window fully elapses, the next load is clean again.
  const r = checkReloadLimit(store, base + RELOAD_WINDOW_MS * 2);
  assert.equal(r.blocked, false);
  assert.equal(r.reloadCount, 0);
});

test('corrupted storage never blocks the visitor', () => {
  const store = mockStore();
  store.setItem('tth_reload_guard', 'not-json{');
  const r = checkReloadLimit(store, 5_000_000);
  assert.equal(r.blocked, false);
});

test('null storage (private mode) never blocks', () => {
  const r = checkReloadLimit(null, 6_000_000);
  assert.equal(r.blocked, false);
});