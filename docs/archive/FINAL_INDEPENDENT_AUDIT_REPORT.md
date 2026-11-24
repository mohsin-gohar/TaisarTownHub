# FINAL INDEPENDENT AUDIT REPORT — TaisarTownHub

**Date:** 2026-11-16
**Repository:** `F:\react\TaisarTownHub` · `origin/main`
**Code/deploy commit:** `a8d217e` — `fix(frontend): keep SPA mounted without VITE_API_URL and add public catalog fallback; remove debug artifacts`
**Auditor:** OpenCode (DeepSeek V4.1 Flash Free), independent execution of builds, tests, and real-browser checks.

---

## 1. Executive Summary

The deployed Vercel site (`https://taisartownhub.vercel.app`) was **completely blank in a real Chromium browser**: `#root` had zero children, `body` height was `0`, text length was `0`, and no images or videos existed. The HTML, CSS, and main JS URL all returned HTTP 200, which initially made this look like a CSS or data problem.

**Root cause (confirmed):** `frontend/src/services/api.ts` threw at module-evaluation time when `VITE_API_URL` was absent:

```
Error: VITE_API_URL is required in production. Set it in your build environment.
    at https://taisartownhub.vercel.app/assets/jsx-runtime-Bmt4m6bx.js
```

Vercel had no `VITE_API_URL`, so the throw aborted the bundle before React mounted. There were no failed network requests and no HTTP 4xx/5xx on assets; only a `pageerror`, which the earlier console-only probe did not capture.

**Fix delivered in `a8d217e`:**

1. The missing-env guard no longer throws; it logs a clear console error and falls back to the same-origin `/api` layout used by `frontend/Dockerfile.prod`.
2. When no API origin is configured (the static Vercel deployment), `getCatalog()` returns a **public-safe bundled catalog** built from `publicSeed.ts` and `agencyVideosSeed.ts`. Agency cards are derived from the public video partners; no dealer/ERP records, CNICs, private commissions, or inquiries enter the public bundle.
3. Committed debug/output artifacts (`*_OUT.txt`, `FINANCE.TXT`, `test_output.txt`, `DebugAgencyContextTest.php`, etc.) were removed.

**Production result after redeploy:** `/`, `/gallery`, `/agencies`, `/properties` all render fully in Chromium. Gallery: 6 video cards, 14 images, 0 broken, modal playback `readyState 4`, `currentTime 1.46s`, no media error. Properties: 44 listing links, 24 images, 0 broken. Agencies: 6 cards, 8 images, 0 broken. Home: 41 images, 33 agency cards, 6 video cards.

**Verdict:** The public marketing website is now functional on Vercel. The overall project is **not production-ready** because Docker, a real ClamAV daemon, a live backend/API, custom-domain DNS/TLS, browser security checks, and approved legacy-ownership mapping remain unexecuted or unresolved. See `FINAL_REMAINING_BLOCKERS.md`.

---

## 2. Scope and Constraints

- No owner files were modified: `Footer`, `AuthPage`, `ContactSection`, `MediaGallerySection`, `ProjectDetailPage`, `ProjectsListingPage`, `AppContext`, `permissions.test`, `permissions`, `types/index.ts`.
- No tests were deleted, skipped, or weakened.
- No production data was deleted or stamped. The only file removed from the repository was committed QA output/debug junk.
- Evidence is from executed commands, real Chromium (Playwright 1.65 / Chromium 1254), and HTTP responses. No Docker, Vercel-log, ClamAV-daemon, or penetration-test result is fabricated.

---

## 3. Critical Finding and Fix — Deployed Blank SPA

### 3.1 Before (real Chromium, deployed `/gallery`)

| Signal | Value |
|---|---|
| HTTP document | `200 text/html` |
| Main JS | `200 application/javascript` |
| CSS | `200 text/css` |
| `#root` children | `0` |
| `document.body.scrollHeight` | `0` |
| Body text length | `0` |
| Images / videos | `0 / 0` |
| Failed requests / HTTP ≥400 | `0 / 0` |
| Uncaught page error | `VITE_API_URL is required in production. Set it in your build environment.` |

Screenshot: `C:\Users\mohsi\AppData\Local\Temp\opencode\qa-shots\deployed-gallery.png` (blank `#0B1220` body).

### 3.2 After (real Chromium, deployed, commit `a8d217e`)

| Route | Body height | Text length | Images | Broken images | Videos / agency cards / property links |
|---|---:|---:|---:|---:|---|
| `/` | 12,876 | 20,301 | 41 | 0 | 6 / 33 / 12 |
| `/gallery` | 2,292 | 4,606 | 14 | 0 | 6 / 6 / 0 |
| `/agencies` | 2,075 | 3,491 | 8 | 0 | 0 / 6 / 0 |
| `/properties` | 5,836 | 13,267 | 24 | 0 | 0 / 22 / 44 |

- Deployed bundle after redeploy: `/assets/index-CJYz7gLM.js` (previously `index-30f5dMp-.js`), CSS `/assets/index-ABOJN43V.css`. Vercel rotates asset hashes on each rebuild; the final verification deployment served `/assets/index-D3Qzp6m-.js` with identical results.
- Page errors: **0** on all four routes.
- Console: only the expected `VITE_API_URL is not set ... using the bundled public catalog.` diagnostic.
- Gallery video: modal opens, `/sample-video.mp4` plays (`readyState 4`, `duration 10.03`, `currentTime 1.46`, `paused false`, `error null`).
- Screenshots: `deployed-fixed-home.png`, `deployed-fixed-gallery.png`, `deployed-fixed-agencies.png`, `deployed-fixed-properties.png`.

### 3.3 Files changed

| File | Change |
|---|---|
| `frontend/src/services/api.ts` | Non-fatal missing-env guard; `/api` production fallback; `buildStaticPublicCatalog()`; `getCatalog()` returns the public catalog when no API origin is configured. |
| 13 committed QA artifact files | Deleted (output logs, debug test, temp text dumps). |

---

## 4. Test and Build Evidence (Executed 2026-11-16)

| Check | Command | Result |
|---|---|---|
| Backend full suite | `php artisan test` (`backend`) | **PASS — 210 tests, 1,814 assertions, 0 failures** |
| Frontend unit suite | `npm test` (`frontend`) | **PASS — 127 tests, 9 suites, 0 failed** |
| TypeScript | `npm run lint` (`tsc --noEmit`) | **PASS — exit 0** |
| Production build + prerender | `npm run build` | **PASS — exit 0; 29 pages + sitemap/robots** |
| Public-bundle leak gate | `npm run check:bundle` | **PASS — no private ERP data or demo credentials in public chunks** |
| PHP syntax | `php -l` on 10 modified/test PHP files | **PASS — no syntax errors** |
| Git whitespace check | `git diff --check` | **PASS — exit 0** |
| Docker runtime | `docker --version` / `docker compose version` | **NOT RUN — command not recognized on this host** |
| Real ClamAV | `clamd --version` / `clamscan --version` | **NOT RUN — commands not recognized; fake daemon only** |
| Entrypoint shell syntax | `bash -n backend/docker/entrypoint.sh` | **NOT RUN — WSL has no installed distribution** |

Targeted suites used in the domain reports:

- `Finance` — **24 passed / 172 assertions**
- `FinanceIsolationTest` — **12 passed / 71 assertions**
- `WorkspaceHardeningTest` — **6 passed / 20 assertions**
- `WorkspaceApprovalTest` — **28 passed / 773 assertions**
- `MalwareScanningTest` — **11 passed / 47 assertions**
- `MalwareUploadEnforcementTest` — **5 passed / 12 assertions**

---

## 5. Local Browser Verification

Real Chromium was used against the local dev server (`http://127.0.0.1:3000`, live Laravel API on `:8080`) and the production preview (`http://127.0.0.1:4173`).

| Area | Evidence |
|---|---|
| Route sweep | `/`, `/projects`, `/properties`, `/agencies`, `/guide`, `/gallery`, `/services`, `/about`, `/contact`, `/login`, `/register`, `/reset-password`, `/verify-email`, `/admin`, `/admin-login`, `/this-page-does-not-exist` loaded with correct titles; 404 page rendered `Page Not Found (404)`. |
| Gallery (API-backed dev) | 6 video cards, 14 images, 0 broken; modal video `readyState 4`, duration 10.03s, playing, no error. |
| Agencies (API-backed dev) | 23 agency cards, 15 images, 0 broken. |
| Properties (API-backed dev) | 38 property links, 21 images, 0 broken. |
| Search | `/properties` keyword `Sector 70` reduced property links 38 → 10. |
| Responsive | 320/375/390/430/768/1024/1366/1440/1920 px: no horizontal overflow; logo 40/44 px; nav hidden below `xl` (1280 px); burger drawer opens/navigates/closes; body scroll lock releases. |
| Mobile drawer | Opens, locks body scroll, navigates to `/projects`, closes, releases scroll lock. |
| Property detail | `/properties?property=prop-11` correct heading/title/3 images; installment buttons switch; empty inquiry submit blocked and focuses name. |
| Property inquiry | Valid submit showed success toast; row persisted in SQLite (`QA Browser Test`) and was then deleted. |
| Contact form | Empty submit blocked/focused name; valid submit showed success toast. No QA-marked contact row remained (`FOUND: 0`); the user-entered `moshin` row was left untouched. |

---

## 6. Security, Multi-Tenancy, and Finance (summary)

- **Multi-tenant:** `WorkspaceApprovalTest` 28/28 and `WorkspaceHardeningTest` 6/6 pass, covering pending-agency denial, token invalidation, deleted-agency fail-closed, cross-agency merge/replace rejection, and ignored client-supplied tenancy fields. `FinanceIsolationTest` 12/12 passes (cross-agency reads/writes 404, commission scoping, deal-number uniqueness).
- **Finance:** 24 finance tests / 172 assertions pass. Deal numbers are generated inside a transaction with `lockForUpdate()` and duplicate-key retry; overpayment, voided-deal, schedule-agency, commission-permission, and audit-log paths are covered.
- **Security:** No critical code-level regression was found in the executed suites, and `check:bundle` confirms no private ERP fields/demo credentials in public chunks. However, **live browser security checks were not executed** (public PII, dealer file-sales, plan limits, staff owner-role escalation, MFA role-switch), and the Vercel deployment has no backend, so auth/admin APIs are not live there.

Detailed reports: `FINAL_SECURITY_AUDIT.md`, `FINAL_MULTI_TENANT_ISOLATION_REPORT.md`, `FINAL_FINANCE_INTEGRITY_REPORT.md`.

---

## 7. Legacy Ownership Status

Read-only report verified: **110 unowned legacy records** — **26 confirmed**, **0 ambiguous**, **84 unresolved**. Unowned rows remain hidden from agency workspaces. No automatic stamping, backfill, or `--apply` was performed. A business-approved mapping plus a verified backup is still required. See `FINAL_MULTI_TENANT_ISOLATION_REPORT.md`.

---

## 8. Docker / ClamAV Status

`docker`, `clamd`, and `clamscan` are not available on this Windows host, and WSL has no installed distribution. Therefore `docker compose build/up`, real-daemon scanning, and container health checks were **not executed**. The ClamAV INSTREAM implementation is verified only against `backend/tests/Support/fake_clamd.php` (11 scanning tests / 47 assertions + 5 upload enforcement tests / 12 assertions). See `FINAL_DOCKER_CLAMAV_RUNTIME_REPORT.md`.

---

## 9. Performance and SEO (build-level only)

- Latest build: `index-s4MCay5r.js` 425.9 KB, CSS 89.71 KB, route-level lazy chunks, 29 prerendered pages, sitemap 9 URLs, robots 6 disallow rules, JSON-LD/canonical/OG metadata present.
- No Lighthouse, Core Web Vitals, throttled-network, or real-device measurement was executed. Images/fonts depend on external Unsplash/Google Fonts. See `FINAL_PERFORMANCE_SEO_REPORT.md`.

---

## 10. Remaining Blockers (full list in `FINAL_REMAINING_BLOCKERS.md`)

1. Docker image build/`compose up` — CLI unavailable.
2. Real ClamAV daemon and live upload rejection — daemon unavailable.
3. Live backend/API for the Vercel deployment — `https://api.taisartownhub.com/api/health` fetch failed; Vercel `/api` returns 404.
4. Custom domain — `https://taisartownhub.com` unreachable (`Transport error`).
5. Browser security checks — not executed.
6. Legacy ownership mapping/backup — not approved/executed.
7. Production secrets, TLS/DNS, SMTP, backups, queue/Redis runtime — environment responsibilities, not verified.
8. Lighthouse/Core Web Vitals — not measured.

---

## 11. Production Approval Decision

**Production-ready: NO.**

**Public marketing site on Vercel: functional.** The blank-page and missing-media defects are fixed and verified in production Chromium.

**Production-approvable: CONDITIONAL**, only after the blockers above are independently executed. No claim of full production readiness is made.
