/**
 * Honest-content helpers for placeholder media and demo catalogs shipped in
 * the public bundle.
 *
 * Static (no-API) builds intentionally bundle a demo catalog so the marketing
 * site is not empty on frontend-only deployments (e.g. Vercel). The media in
 * it — one shared `/sample-video.mp4` walkthrough and stock photography — is
 * NOT real Scheme 45 footage. These helpers let the UI label placeholder
 * media truthfully instead of presenting it as real inventory.
 */

export const DEMO_VIDEO_URL = '/sample-video.mp4';

/**
 * True when this build has no configured API origin (VITE_API_URL) and
 * therefore serves the bundled demo catalog. Mirrors the check in
 * services/api.ts; Vite statically replaces import.meta.env.* at build time,
 * so this is constant-folded per build.
 */
// Guarded the same way as config/appMode.ts: import.meta.env exists in Vite
// (replaced statically at build time) but not under the Node test runner.
const configuredApiUrl: string | undefined =
  typeof import.meta !== 'undefined'
    ? (import.meta as { env?: Record<string, string | undefined> }).env?.VITE_API_URL
    : undefined;
export const isStaticCatalogBuild: boolean = !configuredApiUrl;

/** True when a video URL points at the bundled placeholder sample video. */
export function isDemoVideoUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  const normalized = String(url).trim().toLowerCase().split('?')[0].split('#')[0];
  return normalized === DEMO_VIDEO_URL || normalized.endsWith(DEMO_VIDEO_URL);
}
