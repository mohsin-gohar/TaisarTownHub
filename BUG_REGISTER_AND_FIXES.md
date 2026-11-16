# BUG_REGISTER_AND_FIXES.md — TaisarTownHub

**Date:** 2026-11-14 (updated 2026-11-16) · Register for the Ultimate Enterprise Browser QA pass.
Pre-existing items live in `INITIAL_BUG_REGISTER.md` (K-01…K-09). New findings from these
passes are `N-##`. Every fixed item shows root cause → fix → regression proof.

## New findings & fixes (this pass)

| ID | Sev | Area | Finding | Root cause | Fix | Regression proof |
|---|---|---|---|---|---|---|
| N-01 | 🔴 High | Public catalog | **Zero listings sitewide** — `/api/catalog` returned `"properties"` as a JSON *object* (`{"3":{…}}`), so React's `Array.isArray()` failed and `AppContext` never populated `properties`. Featured sections, `/properties`, hero search counts, and detail deep-links all rendered empty. Triggered whenever a legacy row was skipped (draft/private/payload-deleted), because `array_filter` preserves keys. | `WorkspaceController::catalog()` filtered rows without reindexing | `array_values()` around the filter + map in `WorkspaceController::catalog()` (comment G-12) | `backend/tests/Feature/CatalogShapeTest.php` (2 tests, 9 assertions) + live probe: 16 CTA links render, type filter 16→1→16, keyword search → 3 |
| N-02 | 🟡 Low | Seed/data consistency | Demo agency `agency-demo` was on the **Free Trial plan (staff limit 2)** while the seed created **4 staff** — every staff invite returned 402 `plan_limit_reached` out of the box. | Seed inconsistency (staff > plan limit) | Upgraded via the real admin API (`PUT /admin/agencies/agency-demo/subscription`, plan 4 Enterprise) — no data hand-edits | role-matrix E2E then invited 3 roles → 201; documented in subscription notes |
| N-03 | 🟡 Low | Script/QA tooling | Interaction flows counted the property CTA as `role=button`, but the card CTA is an `<a>` (`role=link`) → vacuous 0/0/0 assertions. 404-recovery asserted a hard-coded hero headline that is CMS-driven. | Stale selectors in the QA script | Fixed to `role=link`; recovery now asserts pathname + populated homepage | probe run: 16 links, filter/search pass |
| N-04 | 🟡 Low | Script/QA tooling | Workflow script misread the legacy **bare-array** contract of `GET /agency/transactions` (`{transactions:[]}` vs `[...]`) → accountant payment step skipped. | Response-shape assumption | Robust extraction (bare array / wrapped) | W3 payment step now records 201 |
| N-05 | 🔴 High | Public listings/search | **Keyword search crashed the listings page** — typing a search term (e.g. "Sector 70-D") on `/properties` threw `TypeError: Cannot read properties of null (reading 'toLowerCase')` inside `filterProperties` and the ErrorBoundary blanked the whole listing area. Triggered by catalog rows carrying `null` optional text fields (description/location/sector/plotNumber/fileNumber), e.g. API-created listings. | `frontend/src/utils/logic.ts` called `.toLowerCase()` / `.includes()` on nullable fields without guards in search and sector filtering | Null-safe guards for title/description/location/sector/plot/file/phase matching and sector facet in `filterProperties` (`logic.ts`) | `frontend/src/utils/logic.test.ts` N-05 regression (null-field search/facet cases); frontend suite **127/127 pass**; **visible-browser live verify:** `/properties` search "Sector 70-D" → "Found 5 matching properties" with cards rendered (no crash, no blank area) |
| N-06 | 🟡 Medium | Admin dashboard UX/permissions | **Operator role saw broken CRM/Staff sections** — agency sidebar exposed "CRM Pipeline" and "Staff & Roles" to an Operator lacking `crm.view`/`agency.manage_staff`, producing console 403s (`/api/agency/leads`, `/api/agency/invitations`) and a user-visible error state ("Could not load the CRM pipeline. `crm.view` is required.") instead of a coherent experience. | `AdminLayout` included those agency tabs unconditionally when `hasAgencyContext && agency.view`, without enterprise-permission gating | Gate sidebar entries in `AdminLayout`: CRM requires `crm.view`; Staff requires `agency.manage_staff` or `staff.view` (pages operator cannot use are hidden; permitted agency pages remain). | Vite hot-reload + **visible-browser verify as Operator:** sidebar no longer shows CRM/Staff, accessible agency pages (Agency Dashboard/Document Vault/Media Studio/Subscription) render without 403/error banners; frontend suite **127/127 pass** |

## Verified-clean areas (evidence)

| Area | Result | Evidence |
|---|---|---|
| Public site crawl (41 routes × desktop/mobile) | clean | `frontend/audit/report.json` (0 real findings; only the expected verify-email 422) |
| Public interactivity (247 click probes) | 0 dead, 0 error | `frontend/audit/interactions.json` |
| Staff lifecycle + 9-role authorisation matrix | **51/51 PASS** | `frontend/audit/role-matrix.json` (invite→accept→matrix→revoke→token dead) |
| Business workflows W1–W4 | **28/28 PASS** | `frontend/audit/workflows.json` (buyer gate, favorites, inquiry→CRM, listing lifecycle, deal+commission, payment, share lifecycle) |
| Admin shell per-role walk | pending re-run | `browser-admin-interactions.mts` (K-09 closure) |

## Notes for the pending phases

- Platform users inherit **no agency** by design; owner actions from scripts send
  `X-Agency-Id: agency-demo` (verified working — deny-by-default holds unscoped, 422
  reached when scoped).
- Throttle (K-02) remains the top ops risk: the interaction run still produced 429s on
  `/api/catalog` under burst load; production profile approval is pending.

## 2026-11-16 — Production blank-page / missing-media closure

| ID | Sev | Area | Finding | Root cause | Fix | Regression proof |
|---|---|---|---|---|---|---|
| N-07 | 🔴 Critical | Deployed frontend | `https://taisartownhub.vercel.app` rendered a **completely blank page** (`#root` 0 children, body height 0, text 0, 0 images/videos) while HTML/CSS/JS all returned 200. | `frontend/src/services/api.ts` threw `VITE_API_URL is required in production...` at module-evaluation time; Vercel had no `VITE_API_URL`, so the entry bundle aborted before React mounted. Earlier probes captured console messages only, missing the uncaught `pageerror`. | Missing-env guard is now non-fatal: logs a diagnostic and falls back to same-origin `/api` (Dockerfile.prod layout). | Playwright/Chromium deployed walk: `/`, `/gallery`, `/agencies`, `/properties` render (body 12,876 / 2,292 / 2,075 / 5,836 px; 0 page errors); deployed asset changed to `index-CJYz7gLM.js`. |
| N-08 | 🔴 High | Public catalog | After the mount fix, the static Vercel deployment still had empty agency/property/video grids because it has no API origin (`/api/catalog` 404). | `getCatalog()` returned `null` when the API was unavailable; public mode initializes catalog state empty by design. | When no API origin is configured, `getCatalog()` returns a public-safe bundled catalog from `publicSeed.ts` + `agencyVideosSeed.ts`; agency cards are derived from the public video partners. No dealer/ERP private records added. | Deployed: gallery 6 videos / 14 images, agencies 6 cards / 8 images, properties 44 links / 24 images, home 41 images; gallery video plays (`readyState 4`, `currentTime 1.46`, `error null`); `npm run check:bundle` PASS. |
| N-09 | 🟡 Low | Repository hygiene | Committed QA artifacts (`backend/*_OUT.txt`, `backend/FINANCE*.TXT`, `backend/test_output.txt`, `frontend/*_OUT.txt`, `frontend/TEST_SUMMARY.txt`, `DebugAgencyContextTest.php`) polluted the repo. | Debug/test output was committed during the previous audit. | Deleted the 13 committed artifacts; temp SQLite verifier and Playwright scripts removed before commit. | `git status` clean after commit `a8d217e`; no test files deleted or weakened (full suites still 210 backend + 127 frontend). |

### Updated verification totals (2026-11-16)

- Backend full suite: **210 passed / 1,814 assertions**.
- Frontend unit suite: **127 passed / 9 suites**.
- `tsc --noEmit`, `npm run build`, `npm run check:bundle`, `git diff --check`: PASS.
- Docker / real ClamAV / live backend / custom domain: still BLOCKED — see `FINAL_REMAINING_BLOCKERS.md`.

