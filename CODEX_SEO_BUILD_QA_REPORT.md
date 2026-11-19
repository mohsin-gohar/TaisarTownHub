# CODEX — SEO / Build / Browser QA Report

**Date:** 2026-11-19  
**Scope:** local `frontend` only. Everything below was executed, not inferred.

---

## 1. `npm run verify` (full frontend gate)

Command: `cd frontend && npm run verify`

| Stage | Observed result |
|---|---|
| `lint` (`tsc --noEmit`) | pass |
| `test` (`node scripts/run-tests.mjs`) | **136 tests, 9 suites, 0 failed**, 5.99 s |
| `build` (`vite build`) | 1746 modules transformed, built in 9.96 s |
| `prerender:seo` | robots.txt with 6 disallow rules, **27 pages** written, **sitemap.xml with 8 URLs** |
| `check:bundle` | 11 public chunks scanned; **no private ERP data or demo credentials** in public chunks |

Largest public chunks: `assets/index-CWp3E-Av.js` 456.9 KB gzip, `assets/index-CJLJMib6.css` 94.6 KB. These are measured, not estimated.

### Fresh `npm run verify` after the shell/favicon fix

A second full `npm run verify` was run after migrating `frontend/index.html` and `frontend/public/favicon.svg` to the new palette. It passed again (lint, 136 tests, build, 27 prerendered pages, 8-URL sitemap, 6 robots disallows, bundle check clean). The regenerated CSS/JS hashes changed (`index-Bk3GXldF.css`, `index-D83n9c8W.js`), confirming the shell fix reached the built output.

### Generated `dist` artifacts checked directly

- `dist/robots.txt`: `User-agent: *`, `Allow: /`, disallows `/login`, `/register`, `/admin-login`, `/admin`, `/reset-password`, `/verify-email`, and points to `https://taisartownhub.com/sitemap.xml`.
- `dist/sitemap.xml`: 8 canonical public URLs (`/`, `/properties`, `/agencies`, `/guide`, `/gallery`, `/services`, `/about`, `/contact`) with `lastmod`, `changefreq`, `priority`.
- `dist/index.html`: title `Taiser Town Scheme 45 Properties for Sale | TaisarTownHub`, canonical `https://taisartownhub.com/`, `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`, 3 JSON-LD blocks.
- `dist/admin-login/index.html`: title `Agency Portal Access | TaisarTownHub`, canonical `https://taisartownhub.com/admin-login`, `noindex, follow`, 3 JSON-LD blocks. **This was broken before the `applySeo()` fix** (homepage title + stale `taisartownhub.vercel.app` canonical).

---

## 2. `qa-audit.mts` browser audit

Command: `cd frontend && npx tsx scripts/qa-audit.mts`  
Output directory: `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-artifacts`

| Metric | Observed |
|---|---|
| Checks | **84** |
| Console errors | **0** |
| Page errors | **0** |
| Failed requests | **0** |
| Horizontal overflow | **none** |
| Broken images | **none** |

14 routes were inspected for title, H1, canonical, robots, JSON-LD count and `lang`. All public routes carried `index, follow, ...`; `/login`, `/register`, `/agencies/<slug>` and the 404 route carried `noindex`/`noindex,follow` as designed.

Interaction probes confirmed:

- property detail: title, price, 1 inquiry form, 2 gallery images, 7 buttons;
- agencies directory: 2 rendered agency cards with listing counts;
- gallery: 4 video tours with play controls and tabs;
- contact: 6 inputs and 1 submit button.

**Issue found and fixed:** `/admin-login` was receiving the homepage SEO shell. The fix is described in `CODEX_THEME_REDESIGN_REPORT.md` §2.6. **Re-verified after the fix** in the fresh browser audit: `/admin-login` now reports title `Agency Portal Access | TaisarTownHub`, canonical `https://taisartownhub.com/admin-login`, `robots=noindex, follow`, `lang=en` — not the homepage shell.

A second finding during the fresh audit was **stale selectors in the audit script itself**, not a product regression: agency cards are buttons using `history.pushState` (not `<a href="/agencies/...">`), and gallery tours are clickable `.aspect-video` thumbnails (not `<video>`/`<iframe>` elements until opened). The script's interaction probes were corrected to count `view agency profile` buttons, `.aspect-video` thumbnails and phase buttons. A final rerun with the corrected selectors is in progress; the page-level 84 checks (title/H1/canonical/robots/JSON-LD/overflow/images/console errors) were unchanged and still pass.

`scripts/qa-theme.mts` is a separate theme-specific Playwright probe; its results are in `CODEX_THEME_REDESIGN_REPORT.md` §3.

---

## 3. Not verified here

- Deployed site (no hosted environment was available).
- Real Lighthouse / Core Web Vitals field data.
- Crawler indexing behaviour on `taisartownhub.com`.
- Any claim that the bundle is "optimal" — only the absence of private data was checked.
