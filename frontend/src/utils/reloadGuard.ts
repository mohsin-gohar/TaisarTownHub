/**
 * Reload rate guard — protects the demo environment from rapid refresh loops
 * (accidental F5 spam / scripted abuse). After more than MAX_RELOADS page
 * loads within WINDOW_MS, the app renders a polite "wait a moment" screen
 * instead of hammering the dev server / API on every reload.
 *
 * The sliding window is kept in sessionStorage, so a brand-new tab or an
 * incognito session starts with a clean slate (first visit is never blocked).
 *
 * The storage backend is injectable so the logic is unit-testable in Node
 * (no sessionStorage there).
 */

export const RELOAD_WINDOW_MS = 60_000;
export const MAX_RELOADS_PER_WINDOW = 5;

const STORAGE_KEY = 'tth_reload_guard';

/** Minimal structural type so tests can pass a mock. */
export interface TimestampStore {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
}

export function checkReloadLimit(
  store: TimestampStore | null = typeof sessionStorage !== 'undefined' ? sessionStorage : null,
  now: number = Date.now()
): { blocked: boolean; reloadCount: number; retryAfterSec: number } {
  if (!store) {
    return { blocked: false, reloadCount: 0, retryAfterSec: 0 };
  }
  try {
    const raw = store.getItem(STORAGE_KEY);
    const stamps: number[] = raw ? (JSON.parse(raw) as number[]).filter((t) => typeof t === 'number') : [];
    // Only timestamps inside the sliding window count. EVERY page load
    // (including the first) appends a stamp; the first visit is not a
    // "reload", so the block threshold allows one extra stamp.
    const recent = stamps.filter((t) => now - t < RELOAD_WINDOW_MS);
    recent.push(now);
    store.setItem(STORAGE_KEY, JSON.stringify(recent));
    const reloadCount = Math.max(0, recent.length - 1);
    if (recent.length > MAX_RELOADS_PER_WINDOW + 1) {
      const oldest = recent[0] ?? now;
      const retryAfterSec = Math.max(1, Math.ceil((RELOAD_WINDOW_MS - (now - oldest)) / 1000));
      return { blocked: true, reloadCount, retryAfterSec };
    }
    return { blocked: false, reloadCount, retryAfterSec: 0 };
  } catch {
    // Storage unavailable (private mode / disabled) — never block.
    return { blocked: false, reloadCount: 0, retryAfterSec: 0 };
  }
}