/**
 * Build-time application mode — the SINGLE source of truth for what demo
 * capabilities exist in this bundle.
 *
 *   'public'      marketing website only. No demo login code, no ERP seed.
 *   'demo'        client-presentation build: PIN-gated instant roles,
 *                 presentation bar, seeded fictional ERP data.
 *   'production'  locked-down ERP for a real operator (real backend only;
 *                 no demo accounts, no instant login, no presentation bar).
 *
 * Vite statically replaces import.meta.env.* at build time, so branches on
 * APP_MODE are constant-folded / dead-code-eliminated in production builds.
 */
export type AppMode = 'public' | 'demo' | 'production';

function resolveMode(): AppMode {
  const raw = String(import.meta.env.VITE_APP_MODE || '').toLowerCase();
  if (raw === 'public' || raw === 'demo' || raw === 'production') return raw as AppMode;
  // Legacy fallback for older .env files that only know VITE_DEMO_MODE.
  return import.meta.env.VITE_DEMO_MODE === 'false' ? 'production' : 'demo';
}

export const APP_MODE: AppMode = resolveMode();

/** Presenter PIN for demo builds. Public warning: this is a speed bump, not security. */
export const DEMO_PIN: string = String(import.meta.env.VITE_DEMO_PIN || '4545');

export const IS_DEMO: boolean = APP_MODE === 'demo';
