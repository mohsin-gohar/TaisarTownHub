# Performance and SEO Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Verified build/SEO artifacts

- `npm run build` → Vite build + prerender: **29 HTML files** (index, properties, projects, agencies, guide, gallery, services, about, contact, login, register, admin-login, admin, reset-password, verify-email — file + directory forms), **sitemap.xml with 9 URLs**.
- `npm run check:bundle`: **10 public chunks**, largest JS chunk ~426 KB; no private ERP data or demo credentials detected.
- Public chunking splits React runtime, icons and route chunks; private ERP code is aliased out of public builds.
- `frontend/src/App.tsx` applies route-aware `<title>`/meta and a 404 screen for unknown routes; earlier Vercel blank-root cause (missing `VITE_API_URL`) was made non-fatal with a same-origin fallback and public-safe bundled catalog.

## Not measured

- Lighthouse Performance / Accessibility / Best Practices / SEO scores — no Lighthouse available; **no scores invented**.
- Core Web Vitals (LCP/CLS/INP), TTFB, real network waterfall under production traffic.
- Cross-browser performance (Chrome/Firefox/Safari/Edge).

## Known caveat

`frontend/nginx.conf` exposes a static `/health` (`return 200 'ok'`) unrelated to the Laravel API; real health is `/api/health`. Monitoring must target the API path.

## Conclusion

Build/prerender/bundle safety is verified; runtime performance and Lighthouse scoring remain external prerequisites.
