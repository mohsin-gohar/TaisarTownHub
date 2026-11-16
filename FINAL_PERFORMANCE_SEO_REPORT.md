# FINAL PERFORMANCE & SEO REPORT — TaisarTownHub

**Date:** 2026-11-16
**Code commit:** `a8d217e`

---

## 1. Build-Level Evidence (Executed)

| Check | Result |
|---|---|
| Production build + prerender | PASS — `vite build` + `tsx scripts/prerender.mts` |
| Prerendered pages | **29** (`index`, `properties`, `projects`, `agencies`, `guide`, `gallery`, `services`, `about`, `contact`, `login`, `register`, `admin-login`, `admin`, `reset-password`, `verify-email` + nested `index.html` variants) |
| Sitemap | **9 URLs** |
| Robots | **6 disallow rules** |
| Main bundle (latest build) | `index-s4MCay5r.js` — **425.9 KB** |
| CSS | `index-*.css` — **89.71 KB** |
| Code splitting | Route/feature chunks (`AgenciesSection`, `PropertiesListingPage`, `PropertyDetailPage`, `ProjectsListingPage`, admin chunks, etc.) |
| Public-bundle leak gate | `npm run check:bundle` — PASS |
| TypeScript | `tsc --noEmit` — PASS |

SEO metadata present in generated HTML: per-route title/description/keywords, canonical URL, Open Graph/Twitter cards, `RealEstateAgent` and `WebSite` JSON-LD, manifest, favicon, theme-color, and a `<noscript>` contact/fallback block.

---

## 2. What Was NOT Measured

- **Lighthouse / PageSpeed Insights** — not executed.
- **Core Web Vitals** (LCP, INP, CLS, TTFB) — not measured on the deployed domain.
- **Throttled network / low-end device** performance — not tested.
- **Image optimization / CDN caching** — public images are remote Unsplash URLs; fonts are remote Google Fonts.
- **Bundle gzip/brotli sizes for the latest build** — earlier build reported ~125 KB gzip for the ~440 KB entry; latest 425.9 KB entry was not re-gzipped in this pass.
- **Server-side response times** — the Vercel deployment is static; no backend latency measurement.
- **Accessibility** — no axe/Lighthouse accessibility audit.

---

## 3. Observations

1. The entry bundle is moderately large (~426 KB minified). Route-level lazy loading is in place, so public routes do not download admin/finance chunks.
2. All six gallery videos share the same local `/sample-video.mp4` (788 KB); no streaming/CDN optimization or poster-based lazy loading beyond the thumbnail UI was measured.
3. Prerendering produces crawlable per-route HTML, sitemap, and robots — good baseline SEO.
4. The deployed site’s canonical/OG URLs point at `https://taisartownhub.vercel.app/`; the intended custom domain `taisartownhub.com` was unreachable during this audit.

---

## 4. Verdict

**Build/SEO baseline: PASS.**

**Performance sign-off: NOT MEASURED.** Run Lighthouse/Core Web Vitals against the final production domain and re-check bundle size after any dependency changes.
