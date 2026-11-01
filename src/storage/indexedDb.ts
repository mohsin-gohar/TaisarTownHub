/**
 * Thin, dependency-free wrapper around the browser IndexedDB API.
 *
 * Used for the audit log and any large/structured client-side storage so
 * the demo never needs a backend to keep an append-only trail. Falls back to
 * `null` (callers use localStorage) when IndexedDB is unavailable.
 */
export interface IDBHandle {
  get<T = unknown>(store: string, key: IDBValidKey): Promise<T | undefined>;
  put(store: string, value: unknown, key?: IDBValidKey): Promise<void>;
  getAll<T = unknown>(store: string): Promise<T[]>;
  delete(store: string, key: IDBValidKey): Promise<void>;
}

const DB_NAME = 'taisartownhub_v2';
const DB_VERSION = 1;
const STORES = ['audit_log', 'kv'];

let dbPromise: Promise<IDBDatabase | null> | null = null;

function openDb(): Promise<IDBDatabase | null> {
  if (typeof indexedDB === 'undefined') return Promise.resolve(null);
  if (dbPromise) return dbPromise;
  dbPromise = new Promise((resolve) => {
    try {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const db = req.result;
        for (const s of STORES) {
          if (!db.objectStoreNames.contains(s)) db.createObjectStore(s, { keyPath: s === 'audit_log' ? 'id' : undefined });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => resolve(null);
      req.onblocked = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
  return dbPromise;
}

export async function getIDB(): Promise<IDBHandle | null> {
  const db = await openDb();
  if (!db) return null;
  const tx = (store: string, mode: IDBTransactionMode) => db.transaction(store, mode).objectStore(store);
  return {
    get: (store, key) =>
      new Promise((resolve) => {
        try {
          const r = tx(store, 'readonly').get(key);
          r.onsuccess = () => resolve(r.result as any);
          r.onerror = () => resolve(undefined);
        } catch {
          resolve(undefined);
        }
      }),
    put: (store, value, key) =>
      new Promise((resolve) => {
        try {
          const r = key !== undefined ? tx(store, 'readwrite').put(value, key) : tx(store, 'readwrite').put(value);
          r.onsuccess = () => resolve();
          r.onerror = () => resolve();
        } catch {
          resolve();
        }
      }),
    getAll: (store) =>
      new Promise((resolve) => {
        try {
          const r = tx(store, 'readonly').getAll();
          r.onsuccess = () => resolve((r.result as any) || []);
          r.onerror = () => resolve([]);
        } catch {
          resolve([]);
        }
      }),
    delete: (store, key) =>
      new Promise((resolve) => {
        try {
          const r = tx(store, 'readwrite').delete(key);
          r.onsuccess = () => resolve();
          r.onerror = () => resolve();
        } catch {
          resolve();
        }
      }),
  };
}