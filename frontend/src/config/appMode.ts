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

export function resolveModeFromEnv(env?: { VITE_APP_MODE?: string; VITE_DEMO_MODE?: string }): AppMode {
  const safeEnv = env || {};
  const raw = String(safeEnv.VITE_APP_MODE || '').toLowerCase().trim();
  if (raw === 'demo') return 'demo';
  if (raw === 'production') return 'production';
  if (raw === 'public') return 'public';

  // Legacy fallback: ONLY when VITE_DEMO_MODE is explicitly 'true' do we activate demo mode.
  if (safeEnv.VITE_DEMO_MODE === 'true') {
    return 'demo';
  }

  // Safe default: Missing or unconfigured environment always defaults to 'public'.
  return 'public';
}

function resolveMode(): AppMode {
  const metaEnv = typeof import.meta !== 'undefined' ? (import.meta as { env?: Record<string, string> }).env : undefined;
  return resolveModeFromEnv(metaEnv);
}

export const APP_MODE: AppMode = resolveMode();

/** Presenter PIN for demo builds. Public warning: this is a speed bump, not security. */
export const DEMO_PIN: string = String(
  (typeof import.meta !== 'undefined' ? (import.meta as { env?: Record<string, string> }).env?.VITE_DEMO_PIN : undefined) || ''
);

export const IS_DEMO: boolean = APP_MODE === 'demo';
