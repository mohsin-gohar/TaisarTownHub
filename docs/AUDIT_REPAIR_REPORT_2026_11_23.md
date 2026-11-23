# TAISARTOWNHUB — COMPLETE INDEPENDENT AUDIT, REPAIR & PRODUCTION HARDENING

Audit date: 2026-11-23. Baseline commit: `46b32f2` (branch `main`, clean tree, `HEAD == origin/main`).
This report records only work actually performed in this mission. Historical root-level `*_REPORT.md`
files are not evidence; they were treated as leads and re-verified where cited.

## A. Executive summary

- **Repository state at start:** root `F:/react/TaisarTownHub`, branch `main`, `HEAD = origin/main =
  46b32f2a0ce6dee248e02bca050536f79101fc3b`, no uncommitted files, no untracked files, no stashes,
  712 tracked files, version 2.0.0. No `AGENTS.md` exists in the repository.
- **Product context:** README, package metadata, seeders and public components consistently identify
  the product as a **sale-only** marketplace for **Taiser Town Scheme 45, Karachi**, with a
  multi-tenant agency ERP. Confirmed by source; no Scheme 45 facts were invented.
- **What was audited:** all 712 tracked files were re-inventoried; three independent deep-audit
  streams (backend security/logic, frontend/WhatsApp/UI, migrations/infrastructure/tests) inspected
  source in parallel; the lead auditor personally read and repaired the highest-risk findings.
- **What was fixed:** 23 confirmed defects were repaired, including the complete agency-specific
  WhatsApp contact restoration, payment-void ledger corruption, sensitive metadata disclosure,
  CRM intra-tenant IDOR, MFA policy bypass, CSV injection, sale-only alert bypass, cross-tenant
  commission references, non-atomic lockout, Stripe webhook gaps, non-idempotent migrations,
  trusted-proxy/CORS/retry infrastructure defects, and a real migration smoke test.
- **What remains:** external blockers (TLS, production secrets, VPS access, staging restore drill,
  cross-browser/Lighthouse/load tests, real ClamAV runtime), several low/medium migration and
  observability gaps listed in section H, and the fact that a line-by-line review of all 712 files
  was **not** completed.
- **Production verdict:** **NOT READY FOR PRODUCTION**. Development/build/test gates pass; release
  gates requiring external infrastructure and evidence do not.

## B. Coverage ledger

Statuses below are deliberately conservative. Inventory is not the same as deep review.

| Status | Definition | This mission |
|---|---|---|
| READ | File content was personally read and checked by the lead auditor | 30+ backend/frontend/config files, every file changed below |
| REVIEWED | File was read by one of the three deep-audit streams with intent to find defects | all 53 migrations, 6 seeders, ~117 backend app files, ~128 frontend source files, 2 workflows, compose/nginx configs |
| TESTED | Exercised by an executed suite (not necessarily read line by line) | 268 PHP files linted; 149 frontend unit tests; 3 WhatsApp E2E tests; backend suite (see F) |
| VERIFIED | A repaired defect has a targeted regression test that was executed | 12 new/updated backend tests, 9 new WhatsApp unit tests, 3 E2E tests |
| BLOCKED | Cannot be executed in this workspace | local Docker/Compose, MySQL 8 runtime (local DB is MariaDB 10.4), raw GitHub job logs, real ClamAV, staging/VPS |
| NOT REVIEWED | Inventoried only; no deep review claim | remaining historical reports/assets and unread source files in the 712-file inventory |

Area inventory (measured with `git ls-files`): backend 308, frontend 209, root 152, documents 16,
docs 15, docker 4, scripts 4, `.github` 2, `.screenshots` 1, nginx 1 (712 total at baseline; new
files added during repair are listed in section I). The per-file status ledger remains
`docs/COMPLETE_FILE_AUDIT_LEDGER.md`.

## C. Consolidated findings

Historical findings numbered #1–#49 could not be located as a single ledger in the repository; the
latest structured ledger is `docs/VERIFIED_BUGS_AND_REPAIRS.md` (entries 1–18). Findings were
therefore verified by content, duplicates merged, and already-fixed leads closed rather than
reopened without evidence.

### C1. WhatsApp customer contact

| ID | Sev | Location | Evidence / root cause | Fix | Test |
|---|---|---|---|---|---|
| W-01 | High | `frontend/src/components/public/AgencyProfilePage.tsx:345,398` | Raw `replace(/\D/g,'')` bypassed normalisation; `0300-9876543` produced `wa.me/03009876543`. | Replaced with `resolveWhatsAppLink()` + accessible `WhatsAppCta`. | E2E `whatsapp.spec.ts` |
| W-02 | High | `PropertyDetailPage.tsx:446-511` | `git show e0c9c34` proved the property WhatsApp CTA was deleted during a UI rewrite; no replacement. | Added property/phone CTA with dynamic title message. | E2E + unit |
| W-03 | High | `AgenciesDirectoryPage.tsx`, `PropertiesListingPage.tsx`, `FeaturedProperties.tsx`, `PropertyQuickViewModal` | `whatsapp`/`contactWhatsApp` data existed but no CTA rendered anywhere. | Added CTAs with destination priority. | E2E |
| W-04 | Medium | `frontend/src/utils/logic.ts:63-77` | Helper emitted invalid double-country-code URLs and a number-less `wa.me/?text=` for missing numbers; no priority resolver. | New `utils/whatsapp.ts` with strict normalisation, priority and null-on-invalid. | 9 unit tests |
| W-05 | Medium | `App.tsx:9`, `WhatsAppFloatingBtn.tsx` | Platform-owner floating button was dead code ("hidden per client request"); error toasts still told users to use WhatsApp. | Kept it dead (do not revive platform-wide contact), removed misleading toast copy, added platform-only contact-page CTA. | E2E |
| W-06 | Low | `config/seo.ts:130`, `AgenciesDirectoryPage.tsx:292` | Sale-only copy leak ("buying or renting", "buyers and tenants"). | Corrected to sale-only wording. | manual/E2E |

### C2. Backend security, finance and data integrity

| ID | Sev | Location | Evidence / repro | Fix | Test |
|---|---|---|---|---|---|
| B-01 | High | `FinanceController.php:785-787`, `TransactionPayment.php`, new migration | `deleted_at` written to a column that did not exist → SQLSTATE 42S22; totals ignored voided rows. | Added `deleted_at` migration, `SoftDeletes`, `$payment->delete()`, excluded voided rows from 4 raw aggregates. | `FinanceIsolationTest::test_own_payment_can_be_voided_and_totals_are_recalculated` |
| B-02 | Medium | `PropertyController.php:913-921` | `with_meta` exposed purchase/selling price and internal notes to any member with `properties.view`. | Require explicit `view_sensitive_cnic`, matching `show()`. | `SensitiveMetadataTest` (2 tests) |
| B-03 | Medium | `InquiryController.php:162-263` | Agent could mutate a colleague's CRM lead; `LeadController::findScoped` already did it correctly. | Added `findScopedForAgent()` to all three mutation endpoints. | `CrmLeadsTest` |
| B-04 | Medium | `EnsureMfaVerified.php:108-120` | `hasPermission($key)` without agency id always returned false for agency members; custom privileged roles bypassed mandatory MFA. | Check each active membership's agency context. | `MfaEnforcementTest` |
| B-05 | Medium | `LeadController.php:301-338` | CSV export wrote `=HYPERLINK(...)` verbatim → spreadsheet formula injection. | `csvSafeCell()` neutralises `= + - @` leading cells. | `CrmLeadsTest` |
| B-06 | Medium | `SavedSearchController.php:95,172`, `PropertySearchFilters.php:88-90`, `SendSavedSearchAlerts.php:51-54` | Legacy/stored `listingPurpose=rent` could email rental listings, bypassing sale-only policy. | Validation allows only `sale`; filter and alert query force sale. | `SavedSearchAlertsTest` |
| B-07 | Medium | `FinanceController.php:926-966` | Commission beneficiary IDs accepted from client without agency validation → cross-tenant references. | Validate dealer and user belong to the deal's agency. | `FinanceIsolationTest` |
| B-08 | Low | `AuthController.php:89-102` | Failed-login counter was a non-atomic read-modify-write. | `lockForUpdate()` inside a DB transaction. | existing auth/lockout tests |
| B-09 | Low | `PaymentController.php:428-459` | Stripe `checkout.session.completed` activated invoices without checking settlement/amount. | Require settled `payment_status` and matching amount when supplied. | `PaymentGatewayTest` |

### C3. Migrations, infrastructure and tests

| ID | Sev | Location | Evidence / repro | Fix | Test |
|---|---|---|---|---|---|
| M-01 | High | `2026_11_10_000016_backfill_enterprise_relations.php:300-308` | Full legacy row overwrote newer finance data including `agency_id`/`dealStatus`. | Gap-fill only null/empty columns. | `BackfillIdempotencyTest` |
| M-02 | High | same file:363 | Commission dedup by transaction id collapsed multiple beneficiaries. | Dedup by transaction + beneficiary + amount. | `BackfillIdempotencyTest` (data path) |
| M-03 | Medium | same file:78-93,174-197,257 | Re-runs reset member roles/status and churned public slugs. | `insertOrIgnore` for members; never patch existing slugs. | `BackfillIdempotencyTest` |
| I-01 | Medium | `docker-compose.yml` backend/worker/scheduler | `TRUSTED_PROXIES` absent → nginx container IP used for rate limits, audit IP and MFA location checks. | Set trusted proxy CIDRs; `SESSION_SECURE_COOKIE=true`. | YAML parse |
| I-02 | Medium | `nginx/nginx.conf`, `docker/nginx.ci.conf` | CORS preflight reflected any Origin, bypassing the backend allowlist. | `map $http_origin $tth_cors_origin` allowlist + `Vary: Origin`. | real nginx 1.27.5 runtime test |
| I-03 | Medium | both nginx configs | `fastcgi_next_upstream ... http_500` replayed non-idempotent POSTs. | Removed `http_500`. | real `nginx -t` |
| I-04 | Medium | `backend/tests/Feature/MigrationSmokeTest.php` | Placeholder `assertTrue(true)` named "sqlite" while suite is MySQL-only. | Real fresh-install schema assertions. | executed |
| I-05 | Low | `backend/Dockerfile.prod`, `vercel.json` | Quarantine directory absent in production image; root Vercel config lacked security headers. | Added directory and headers. | build/lint |

## D. Fixed issues (verification summary)

- New backend regression tests: `SensitiveMetadataTest`, `BackfillIdempotencyTest`, plus targeted
  cases in `FinanceIsolationTest`, `CrmLeadsTest`, `SavedSearchAlertsTest`, `MfaEnforcementTest`.
- New frontend unit tests: `frontend/src/utils/whatsapp.test.ts` (9 tests) and new E2E spec
  `frontend/e2e/whatsapp.spec.ts` (3 tests).
- nginx: four configs pass real `nginx -t` on nginx 1.27.5; runtime preflight returned 204 with
  `Access-Control-Allow-Origin: http://localhost` and `Vary: Origin` for an allowed origin, and
  **no** ACAO header for `https://evil.test`.
- Full verification commands and results are in section F.

## E. WhatsApp restoration report

- **Destination priority implemented:** property `contactWhatsApp` → responsible agent public
  WhatsApp (when exposed) → agency `whatsapp` → agency public `phone`. No platform-owner fallback;
  no private staff numbers; no invented numbers.
- **Normalisation:** strips formatting, converts Pakistani local `03xx`/`0092`/bare 10-digit to
  `92...`, removes duplicated country codes, accepts valid international 10–15 digit numbers, and
  returns `null` for missing/invalid numbers so the CTA is hidden.
- **Messages:** exact approved agency template; property template uses the real property title,
  falling back to "this property" (no `undefined`). Both URL-encoded.
- **UI:** consistent `WhatsAppCta` component with icon, label, accessible name, `target="_blank"`,
  `rel="noopener noreferrer"`, responsive variants; mounted on agency directory cards, agency
  profile header + contact section, property cards (listing/featured/agency profile), property
  detail, quick-view modal and the platform's own contact page.
- **Agency control:** `AdminAgencyDashboard` now has a "Public contact & WhatsApp" form using
  `api.updateAgencyProfile`, with client-side validation and normalised storage.
- **Mobile/desktop:** E2E ran in Chromium desktop; layouts use `flex-wrap` and full-width buttons on
  small screens. Real device browser audit remains BLOCKED (see H).
- **Limitations:** no WhatsApp Business API notification driver (no provider credentials); this
  feature is click-to-chat only, as before.

## F. Test results (final worktree)

| Command | Result |
|---|---|
| `cd backend && php artisan test` | **371 passed / 3113 assertions, 0 failed**, exit 0 (246.03 s, local MariaDB over MySQL protocol) |
| `cd frontend && npm run verify` | EXIT 0 — tsc clean, 149/149 unit tests, build + prerender (9 sitemap URLs), bundle check OK |
| `npx playwright test e2e/whatsapp.spec.ts --workers=1` | 3 passed (Chromium, 30.4 s), EXIT 0 |
| `npx playwright test --workers=1` (full suite) | **90 passed / 0 failed (Chromium, 5.9 min)**, EXIT 0 |
| `php -l` on 268 tracked + new PHP files | 0 syntax errors |
| `composer audit` | 0 advisories |
| `npm audit --audit-level=high` | 0 vulnerabilities |
| YAML/JSON parse of compose, workflows, vercel.json | OK |
| Real nginx 1.27.5 `nginx -t` on 4 configs | all exit 0 |
| Runtime CORS preflight | allowed origin echoed + `Vary`; disallowed origin not echoed |

Local database is MariaDB 10.4.32 via MySQL protocol; MySQL 8 is authoritative in GitHub Actions.
No Docker CLI/daemon is available locally, so Compose startup and image builds remain CI-only.

## G. Production release checklist

| Gate | Status |
|---|---|
| Backend suite | PASS (local MySQL protocol; CI MySQL 8 authoritative) |
| Frontend typecheck/unit/build/prerender/bundle | PASS |
| WhatsApp unit + E2E | PASS |
| PHP lint / dependency audits / YAML | PASS |
| nginx syntax + CORS preflight | PASS (real nginx binary) |
| Docker Compose integration + restore drill | PASS in previous CI run on base commit; not re-run locally (BLOCKED) |
| Role-based access / tenant isolation | PASS (dedicated backend suites + new regression tests) |
| Public Scheme 45 sale-only marketplace | PASS (server + client filters; legacy rental alert path closed) |
| Restore drill | PASS on base commit (58 tables, 256 indexes, 95 FKs, cleanup verified) |
| Cross-browser / Lighthouse / load tests | NOT TESTED |
| TLS certificates / production secrets / VPS access / staging restore | BLOCKED (owner action) |
| Real ClamAV runtime | BLOCKED (CI disables scanning; fake scanner in tests) |

## H. Remaining blockers and unresolved findings

1. External: real TLS, `APP_KEY`/DB/Redis/SMTP/payment/OAuth/monitoring credentials, VPS access,
   staging restore drill, cross-browser/Lighthouse/load evidence, ClamAV daemon verification.
2. Migration upgrade paths not fully repaired (A1b NOT NULL ordering, A5–A15 from the migration
   audit: agency auto-verification, finance metadata loss, unsafe rollbacks, duplicate indexes,
   missing FKs, seeder environment guards). No destructive migration rehearsal was performed.
3. Frontend remaining low/medium gaps: `submitInquiry` collapses 422/429/500 to a generic toast
   (D8); static seed catalog exposes demo `phone`/`whatsApp` inconsistently with the backend public
   allowlist (D9); inquiry phone validation is weak (D10); stale-state race in
   `AgenciesDirectoryPage` (D13); detail pages excluded from sitemap/prerender and non-slug
   canonicals (D16).
4. Infrastructure remaining low gaps: `APP_KEY` not a required Compose variable (B5), Redis/MySQL
   passwords visible in process arguments (B7), backup retention docs mismatch and quarantine not
   in backup (B11), deprecated root Dockerfile still shippable (B12), short-form `depends_on` for
   nginx (B13), E2E not wired into CI (B4).
5. Coverage: a line-by-line review of all 720 tracked files was not completed; CI/Docker runtime was not
   re-executed locally.

## I. Final Git status

- Branch: `main`
- Baseline at audit start: `46b32f2a0ce6dee248e02bca050536f79101fc3b` (clean, equal to `origin/main`)
- Code/repair commit: `95497fb52de1c3ee0fb5c9e9944e964973078356` — 33 files modified, 7 files added, 0 deleted
- This report is committed as the final local commit and both commits were fast-forward pushed to
  `origin/main` on explicit owner instruction. No force push, reset or history rewrite was used.
- Working tree: clean after the final commit
- Deployment: **none**. No production system, database or secret was touched.
