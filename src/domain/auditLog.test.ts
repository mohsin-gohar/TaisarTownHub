import test from 'node:test';
import assert from 'node:assert/strict';
import { logAudit, getAuditLog, clearAuditLogCache } from './auditLog';

/** Minimal localStorage polyfill for the node test runner (no browser APIs). */
function installFakeLocalStorage(): void {
  const store = new Map<string, string>();
  (globalThis as { localStorage?: unknown }).localStorage = {
    getItem: (k: string) => store.get(k) ?? null,
    setItem: (k: string, v: string) => {
      store.set(k, v);
    },
    removeItem: (k: string) => {
      store.delete(k);
    },
    clear: () => store.clear(),
  };
}

test('logAudit appends entries newest-first with ids and timestamps', () => {
  installFakeLocalStorage();
  clearAuditLogCache();

  const first = logAudit({ actor: 'Syed Moazzam Ali', role: 'super_admin', action: 'login', target: 'usr-1' });
  const second = logAudit({
    actor: 'Syed Moazzam Ali',
    role: 'super_admin',
    action: 'status_change',
    target: 'tx-1',
    detail: 'Draft -> Negotiation',
  });

  const log = getAuditLog();
  assert.equal(log.length, 2);
  assert.equal(log[0].id, second.id);
  assert.equal(log[1].id, first.id);
  assert.notEqual(first.id, second.id);
  assert.ok(first.at.length > 0, 'entries carry an ISO timestamp');
  assert.equal(log[0].detail, 'Draft -> Negotiation');
});

test('audit history survives a cache reset (persisted append-only)', () => {
  installFakeLocalStorage();
  clearAuditLogCache();

  logAudit({ actor: 'Saleem Siddiqui', role: 'admin', action: 'void_transaction', target: 'tx-9', detail: 'duplicate deal' });
  clearAuditLogCache(); // forces a re-read from storage

  const log = getAuditLog();
  assert.equal(log.length, 1);
  assert.equal(log[0].action, 'void_transaction');
  assert.equal(log[0].target, 'tx-9');
});

test('works without any storage backend (node / private mode)', () => {
  // Remove the polyfill - readCache/writeCache must swallow the failure.
  (globalThis as { localStorage?: unknown }).localStorage = undefined;
  delete (globalThis as { localStorage?: unknown }).localStorage;
  clearAuditLogCache();

  const entry = logAudit({ actor: 'Kamran Haider', role: 'manager', action: 'verify_document', target: 'doc-3' });
  assert.equal(entry.action, 'verify_document');
  assert.ok(entry.id.startsWith('aud-'));
  clearAuditLogCache();
});
