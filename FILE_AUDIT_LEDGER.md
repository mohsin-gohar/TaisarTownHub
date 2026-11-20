# File Audit Ledger — TaisarTownHub

**Audit HEAD:** `28c32edb7bd9f6267e5a58bb97239b371c4b1084`
**Audit date:** 2026-11-20
**Working tree:** uncommitted changes are intentional and were not committed/pushed.

This ledger is the file-level audit companion to `FINAL_RELEASE_GATE_REPORT.md`, `SECURITY_AUDIT_REPORT.md`, `PRODUCTION_READINESS_REPORT.md`, `BUGS_FIXED_AND_REMAINING.md` and `TEST_VERIFICATION_REPORT.md`.

## 1. Google OAuth

| Area | Files inspected | Verdict / fixes |
|---|---|---|
| Backend redirect/state | `backend/app/Http/Controllers/Api/AuthController.php`, `backend/config/services.php`, `backend/routes/api_definitions.php` | Verified. Server-side Socialite exchange, one-time cached state, required per-tab nonce hash, `config('app.frontend_url')` redirect. Unconfigured credentials return 503. |
| Backend callback | `AuthController.php` | Verified. State consumed before Google call, verified-email enforcement, linking by normalized email, new pending accounts, account-status gates, MFA challenge, single-use exchange code. |
| Backend account model | `backend/app/Models/User.php`, `backend/app/Http/Controllers/Api/AuthController.php` | Verified. `authenticationBlockedReason()` blocks suspended/inactive/rejected; password and OAuth share the same status rules; case-insensitive email lookup/normalisation. |
| Backend tests | `backend/tests/Feature/GoogleOAuthTest.php` | Verified. 21 tests / 162 assertions; targeted full run **35/35 passed, 267 assertions** after latest changes. |
| Frontend auth UI | `frontend/src/components/public/AuthPage.tsx`, `frontend/src/services/api.ts` | Verified. Per-tab nonce stored in `sessionStorage`, callback rejects missing/mismatched nonce before MFA or code exchange, code is cleared before exchange, specific error messages. |
| Related cleanup | `User.php`, `AgencyController.php`, tests | Verified lower-case email normalisation on register/agency register, forgot/reset. |

## 2. Storage quota removal

| Area | Files inspected | Verdict / fixes |
|---|---|---|
| Vault/media upload gates | `DocumentController.php`, `MediaController.php`, `DealerFilesController.php`, `PlanLimits.php`, `Agency.php` | Verified. Storage quota/trial enforcement removed from all backend paths; count limits remain. Per-file size, extension allowlist, MIME sniffing, malware scan, private disk, and tenant scoping remain. |
| Usage/oversight | `AdminAgencyOversightController.php`, `DocumentController::usage()`, `AdminAgencyOversight.tsx`, `AdminDocumentVault.tsx`, `AdminMediaStudio.tsx`, `AdminSubscription.tsx`, `AgencyOnboarding.php`, `EnterpriseSeeder.php` | Verified monitoring-only. Quota fields, upload-blocking UI, trial banners and false “uploads blocked” claims removed. |
| Stale comments/docs | Same files | Updated to say unlimited storage in this release. |
| API response consistency | `AgencyStorageOversightTest.php`, `FileVaultSecurityTest.php`, `AgencyOnboarding.php` | Verified via tests: uploads succeed despite legacy `storage_mb`, legacy plan values never block/suspend. |
| Remaining quota terms | `PlanLimits.php`, seeder comments, UI | Only legacy schema fields and count-limit semantics remain; no enforcement path calls `storageBlock`. |

## 3. Featured Agencies free launch

| Area | Files inspected | Verdict / fixes |
|---|---|---|
| Public roster/badge | `FeaturedAgencyController.php`, `AgencyPromotion.php`, `PropertyController.php`, `App.tsx`, public pages | Verified. Badge is always `Featured`; `is_paid=false`; fallback roster is not branded; no Sponsored badge. |
| Service/rules | `AgencyPromotionService.php`, `AdminFeaturedAgencyController.php`, `FeaturedAgencyController.php` | Verified. Eligibility, slot caps, ordering, expiry, public profile fallback. Payment fields are neutralized on create/update; free launch is enforced even if clients send legacy payment values. |
| Admin UI | `AdminFeaturedPromotions.tsx` | Verified. Removed price/payment/revenue/“Mark paid” UI; packages are free duration presets; stats include `free_launch`. |
| Backend tests | `FeaturedAgencyPromotionTest.php` | Verified. **7/7 passed** after rewrite; legacy payment fields neutralized; truthful Featured badge. |

## 4. Sale-only marketplace

| Area | Files inspected | Verdict / fixes |
|---|---|---|
| Public marketplace | `PropertyController.php`, `AgencyController.php`, `App.tsx`, `AgencyProfilePage.tsx`, `PropertiesListingPage.tsx`, `api.ts` | Verified. Public properties are sale-only; public agency profile/listings/stats forced sale-only; `rent_listing_count` returns 0. Internal/legacy rent rows remain for platform workflows only. |
| API/browser QA | `sale-only.smoke.spec.ts`, `public-pages.spec.ts`, `agency-marketplace.smoke.spec.ts`, Playwright logs | Verified public/sale-only/interactive/agency specs pass; public `/api/properties?purpose=sale` returns sale data. |

## 5. Tenant isolation, security, SEO, responsive, infra

| Area | Files inspected | Verdict / notes |
|---|---|---|
| Tenant isolation | `AuthorizesApi.php`, `AgencyContext.php`, `DocumentController`, `MediaController`, `DealerFilesController`, `FinanceController`, tests | Verified from source + targeted tests; database/browser evidence is in `BUGS_FIXED_AND_REMAINING.md`. |
| Uploads/security | `DocumentController`, `MediaController`, `SecureFileStore.php`, `UploadSecurity.php`, `MalwareScanner.php`, `ClamAVScanner.php` | Verified static behavior; `MalwareScanningTest` 11/11 passed when run isolated. Live ClamAV runtime not verified. |
| SEO | `dist/index.html`, `dist/sitemap.xml`, `dist/robots.txt`, `scripts/prerender.mts`, `__seo.mjs` | Verified prerender/SEO bundle; 27 pages + sitemap generated; `check:bundle` passed. |
| Responsive | `responsive.spec.ts`, logs | Verified 7/7 after isolated rerun; desktop 1280MB overflow issue was Playwright artifact-cleanup, not app overflow. |
| Production config | `app:production-check`, `migrate:status`, `optimize`, `route:list` | Verified 20 pass / 1 warn / 0 fail; schema status up to date; 377 API route lines listed. |
| Infra | `docker/`, `docker-compose.yml`, MySQL binaries, port probes | Verified configs exist; runtime blockers recorded: Docker not installed, MySQL reachable on 3306 is false despite binaries present. |

## 6. Aggregate notes

- Live OAuth E2E remains **blocked** by missing real Google credentials (`config/services.google.*` is null in local env).
- Commit/push/deploy was **not** performed.
- Full backend suite: **320 passed, 2587 assertions** on completed run; targeted rerun after latest changes **35 passed, 267 assertions**.
- Browser: public/sale-only/interactive/agency/security/responsive pass; role/admin E2E has environment-specific live-login failures and is not a code-defect claim.
- Test/artifact files created under `.screenshots/` are untracked; `backend/audit-routes.txt` is an audit artifact.
