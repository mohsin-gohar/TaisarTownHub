/**
 * Property comparison / shortlist helpers.
 *
 * Kept framework-free so the selection rules (dedupe, max size, share link
 * parsing) are unit-testable without a DOM. The UI stores the selection in
 * localStorage under a namespaced key; a share link carries the same ids in
 * `?compare=id1,id2` so a shortlist can be sent to a buyer.
 */
export const COMPARE_STORAGE_KEY = 'taisartownhub_compare_ids';
export const MAX_COMPARE_ITEMS = 4;

export interface CompareToggleResult {
  ids: string[];
  added: boolean;
  limitReached: boolean;
}

export function sanitizeCompareIds(value: unknown, max = MAX_COMPARE_ITEMS): string[] {
  const raw = Array.isArray(value) ? value : [];
  const seen = new Set<string>();
  const ids: string[] = [];

  for (const item of raw) {
    if (typeof item !== 'string') continue;
    const id = item.trim();
    if (!id || seen.has(id)) continue;
    seen.add(id);
    ids.push(id);
    if (ids.length >= max) break;
  }

  return ids;
}

export function toggleCompareId(ids: string[], id: string, max = MAX_COMPARE_ITEMS): CompareToggleResult {
  const cleanId = typeof id === 'string' ? id.trim() : '';
  const current = sanitizeCompareIds(ids, max);

  if (!cleanId) return { ids: current, added: false, limitReached: false };

  if (current.includes(cleanId)) {
    return { ids: current.filter((value) => value !== cleanId), added: false, limitReached: false };
  }

  if (current.length >= max) {
    return { ids: current, added: false, limitReached: true };
  }

  return { ids: [...current, cleanId], added: true, limitReached: false };
}

export function parseCompareIdsFromSearch(search: string, max = MAX_COMPARE_ITEMS): string[] {
  try {
    const params = new URLSearchParams(search.startsWith('?') ? search : `?${search}`);
    const raw = params.get('compare') || '';
    return sanitizeCompareIds(raw.split(','), max);
  } catch {
    return [];
  }
}

export function buildCompareShareUrl(baseUrl: string, ids: string[]): string {
  const clean = sanitizeCompareIds(ids);
  if (!baseUrl || clean.length === 0) return baseUrl;
  const separator = baseUrl.includes('?') ? '&' : '?';
  return `${baseUrl}${separator}compare=${encodeURIComponent(clean.join(','))}`;
}

type StorageLike = Pick<Storage, 'getItem' | 'setItem'>;

function defaultStorage(): StorageLike | null {
  try {
    return typeof window !== 'undefined' ? window.localStorage : null;
  } catch {
    return null;
  }
}

export function readCompareIds(storage: StorageLike | null = defaultStorage()): string[] {
  if (!storage) return [];
  try {
    return sanitizeCompareIds(JSON.parse(storage.getItem(COMPARE_STORAGE_KEY) || '[]'));
  } catch {
    return [];
  }
}

export function writeCompareIds(ids: string[], storage: StorageLike | null = defaultStorage()): string[] {
  const clean = sanitizeCompareIds(ids);
  if (storage) {
    try {
      storage.setItem(COMPARE_STORAGE_KEY, JSON.stringify(clean));
    } catch {
      /* storage unavailable — selection stays in React state */
    }
  }
  return clean;
}
