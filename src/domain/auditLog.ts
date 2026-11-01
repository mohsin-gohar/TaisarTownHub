/**
 * Append-only audit log (P0-6 / Phase 4).
 *
 * Sensitive actions (login, role switch, void, payout, verify, delete/restore,
 * payment recording) append a record here. The log is persisted to localStorage
 * (synchronous, instant UI) and best-effort mirrored to IndexedDB via the
 * storage wrapper. Records are never mutated or deleted — only appended.
 */
import { getIDB } from '../storage/indexedDb';

export interface AuditEntry {
  id: string;
  at: string;          // ISO timestamp
  actor: string;       // user name + role
  role: string;
  action: string;      // e.g. 'void_transaction', 'pay_commission'
  target?: string;     // record id
  detail?: string;
}

const STORAGE_KEY = 'taisartownhub_v2_audit_log';
const MAX_ENTRIES = 500;

let cache: AuditEntry[] | null = null;

function readCache(): AuditEntry[] {
  if (cache) return cache;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    cache = raw ? (JSON.parse(raw) as AuditEntry[]) : [];
  } catch {
    cache = [];
  }
  return cache;
}

function writeCache(entries: AuditEntry[]) {
  cache = entries.slice(0, MAX_ENTRIES);
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cache));
  } catch {
    /* ignore quota errors */
  }
  // Best-effort mirror to IndexedDB (async, non-blocking).
  void (async () => {
    const idb = await getIDB();
    if (!idb) return;
    for (const e of cache!) await idb.put('audit_log', e);
  })();
}

export function logAudit(entry: Omit<AuditEntry, 'id' | 'at'>): AuditEntry {
  const full: AuditEntry = {
    id: `aud-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    at: new Date().toISOString(),
    ...entry,
  };
  const next = [full, ...readCache()];
  writeCache(next);
  return full;
}

export function getAuditLog(): AuditEntry[] {
  return readCache();
}

export function clearAuditLogCache() {
  cache = null;
}