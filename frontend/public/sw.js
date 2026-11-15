/**
 * G-17 — TaisarTownHub service worker.
 *
 * Scope rules (deliberately conservative for a multi-agency SaaS that also
 * serves PRIVATE documents through authenticated endpoints):
 *
 *  - NEVER touch cross-origin requests (the API lives on its own origin; the
 *    worker must never see, cache or replay its traffic).
 *  - NEVER cache non-GET requests.
 *  - Same-origin `/assets/*` (Vite content-hashed bundles) → cache-first:
 *    the URL changes whenever the content does, so a hit is always current.
 *  - Every other same-origin GET (HTML shell, public images, fonts…) →
 *    network-first, falling back to the cache when offline.
 *  - Offline fallback for navigations serves the cached shell.
 *
 * Bump CACHE_VERSION to invalidate every cache on deploy.
 */
const CACHE_VERSION = 'v1';
const SHELL_CACHE = `tth-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `tth-assets-${CACHE_VERSION}`;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) => cache.addAll(['/index.html', '/manifest.webmanifest', '/favicon.svg']))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

const isStaticAsset = (url) => url.pathname.startsWith('/assets/');

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only same-origin, plain GETs are ever cacheable.
  if (request.method !== 'GET' || request.url.origin !== self.location.origin) {
    return;
  }

  const url = new URL(request.url);

  if (isStaticAsset(url)) {
    // Cache-first: content-hashed URLs never go stale.
    event.respondWith(
      caches.match(request).then(
        (hit) =>
          hit ||
          fetch(request).then((response) => {
            if (response.ok) {
              const copy = response.clone();
              caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
            }
            return response;
          }),
      ),
    );
    return;
  }

  // Network-first for HTML and other same-origin files.
  if (request.mode === 'navigate' || url.pathname.startsWith('/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok && request.mode === 'navigate') {
            const copy = response.clone();
            caches.open(SHELL_CACHE).then((cache) => cache.put('/index.html', copy));
          }
          return response;
        })
        .catch(() =>
          caches
            .match(request.mode === 'navigate' ? '/index.html' : request)
            .then(
              (hit) =>
                hit ||
                (request.mode === 'navigate'
                  ? caches.match('/index.html')
                  : Response.error()),
            ),
        ),
    );
  }
});
