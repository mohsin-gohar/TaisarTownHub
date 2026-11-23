# Complete File Audit Ledger — TaisarTownHub

Audit baseline: `e419f2a9d82f977bec28abb8cba0945581827cd6` (local `main` == `origin/main`, clean tree, verified with `git fetch origin`).
Repository: `mohsin-gohar/TaisarTownHub`. Audit date: 2026-11-23.

## Coverage policy (honesty first)

This ledger inventories **all 701 tracked files** (`git ls-files`) and records a review status for
each. Statuses are deliberately conservative:

| Status | Meaning |
|---|---|
| `REVIEWED` | File content was read and checked for defects during this audit; findings/fixes are listed in `docs/VERIFIED_BUGS_AND_REPAIRS.md`. |
| `TEST-COVERED` | File is exercised by a real executed test suite (backend 362 tests, frontend 149 tests, Playwright 84 tests) but was not line-by-line read. |
| `INVENTORIED` | File is present and its purpose is path-derived; it was NOT individually read in this session. No claim of audit coverage is made for it. |
| `HISTORICAL` | Prior report/snapshot — preserved, NOT treated as evidence; claims re-verified only where cited. |
| `ASSET` | Binary/static asset — inventoried, reference-checked only where noted. |
| `BLOCKED` | Could not be executed/verified here (reason stated). |

**No claim of 100% file-by-file review is made.** The inventory proves *enumeration*, not line-by-line
coverage. This distinction is intentional and is reflected in the completion estimate.

## Inventory summary

| Area | Tracked files | Note |
|---|---:|---|
| `backend/` | 308 | PHP source, config, migrations, seeders, tests, assets |
| `frontend/` | 209 | React/TS source, tests, E2E, scripts, public assets |
| `documents/` | 16 | Historic specification documents (HISTORICAL) |
| `docs/` | 5 | OpenAPI + release-gate documents |
| `scripts/` | 4 | Backup, restore drill |
| `docker/`, `nginx/`, `.github/` | 7 | Runtime/CI configuration |
| Root files (reports, manifests, assets) | ~152 | Mostly HISTORICAL reports and audit logs |

Extensions: 265 `.php`, 153 `.md`, 80 `.tsx`, 62 `.ts`, 26 `.txt`, 20 `.mts`, 12 `.png`,
11 `.json`, 7 `.jpg`, 6 `.conf`, 4 `.yml`, 4 `.mjs`, plus manifests/lockfiles.

## Files read and checked in this audit (`REVIEWED`)

- `.github/workflows/ci.yml`, `.github/workflows/docker-ci.yml`
- `backend/Dockerfile.prod`, `backend/docker/entrypoint.sh`, `backend/scripts/healthcheck.php`,
  `backend/scripts/wait-for-db.php`, `backend/scripts/assert-db-driver.php`
- `backend/config/database.php`, `backend/config/queue.php`, `backend/config/cors.php`,
  `backend/bootstrap/app.php`, `backend/phpunit.xml`, `backend/composer.json`, `backend/composer.lock`
- `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php`,
  `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php`
- `backend/app/Http/Controllers/Api/AuthorizesApi.php`, `DocumentController.php` (download/
  preview/delete paths), `HealthController.php`, `AgencyController.php::index`, `MapController::publicIndex`,
  `AuthController.php` (Google OAuth state/nonce, suspended accounts)
- `backend/app/Http/Middleware/AgencyContext.php`, `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Models/User.php`, `Agency.php`, `Property.php` (`$fillable` mass-assignment review)
- `backend/routes/api.php`, `backend/routes/api_definitions.php`
- `Dockerfile.prod` (root legacy image — reviewed and deprecated), `docker-compose.yml`,
  `docker-compose.ci.yml`, `docker/nginx.ci.conf`, `nginx/nginx.conf`,
  `frontend/nginx.conf`, `frontend/Dockerfile.prod`
- `scripts/backup.ps1`, `scripts/backup.sh`, `scripts/restore-drill.ps1`, `scripts/wait-for-compose-ready.sh`
- `README.md`, `docs/*`, `frontend/playwright.config.ts`, `frontend/e2e/helpers/adminLogin.ts`,
  `frontend/e2e/responsive.spec.ts`, `frontend/e2e/role-based.spec.ts`, `frontend/e2e/admin-pages.spec.ts`
- `backend/.env.example`, `frontend/.env.example`, `.gitignore`, root `package.json`, `frontend/package.json`

## Test evidence supporting broad (not per-file) coverage

- Backend: 362 tests / 3054 assertions on a fresh MySQL 8 database — see `docs/TEST_EVIDENCE.md`.
- Frontend: `npm run verify` — TypeScript, 149 unit tests, build+prerender, bundle privacy check.
- Browser: Playwright 84/84 (Chromium) against the real API on MySQL.
- Restore: real isolated MySQL import drill PASS (58 tables, critical row counts, indexes, vault).
- Dependency audits: `composer audit` 0 advisories; `npm audit --audit-level=high` 0 vulnerabilities.

## Blocked / not verifiable here

| Item | Reason |
|---|---|
| Docker image build & full Compose stack | No Docker CLI/daemon in this workspace (documented, not claimed green locally). CI run results were read via GitHub's public Jobs API. |
| GitHub Actions raw log download | `/actions/jobs/{id}/logs` returned HTTP 403 without credentials; job/step statuses were obtained from the public Jobs API instead. |
| Firefox/WebKit, Lighthouse, load tests | Tooling/targets not configured; not claimed. |
| Historical root reports (~150 `*.md`, `audit-*.txt`) | Marked HISTORICAL; not used as evidence. |

## Files ADDED by this audit (not in the baseline 701-file inventory)

The baseline inventory below is the `git ls-files` snapshot taken at audit start. This audit added
five new release documents, all reviewed as part of the work:
`docs/COMPLETE_FILE_AUDIT_LEDGER.md`, `docs/FINAL_INDEPENDENT_AUDIT.md`,
`docs/VERIFIED_BUGS_AND_REPAIRS.md`, `docs/PRODUCTION_BLOCKERS.md`, `docs/TEST_EVIDENCE.md`.

## Full inventory (path → derived purpose → status)

| Path | Purpose (path-derived) | Status |
|---|---|---|
| `.github/workflows/ci.yml` | GitHub Actions workflow | REVIEWED |
| `.github/workflows/docker-ci.yml` | GitHub Actions workflow | REVIEWED |
| `.gitignore` | Manifest/config/asset | REVIEWED |
| `.nvmrc` | Manifest/config/asset | INVENTORIED |
| `.screenshots/api-properties.json` | Manifest/config/asset | INVENTORIED |
| `AGENCY_MARKETPLACE_IMPLEMENTATION_REPORT.md` | Historical report/note | HISTORICAL |
| `API_DATABASE_VERIFICATION_REPORT.md` | Historical report/note | HISTORICAL |
| `API_DOCS.md` | Historical report/note | INVENTORIED |
| `API_DOCUMENTATION.md` | Historical report/note | INVENTORIED |
| `API_ROUTE_AND_AUTHORIZATION_AUDIT.md` | Historical report/note | HISTORICAL |
| `AUDIT_REPORT.md` | Historical report/note | HISTORICAL |
| `BACKEND_DATABASE_API_AUDIT.md` | Historical report/note | HISTORICAL |
| `BACKUP_GUIDE.md` | Historical report/note | INVENTORIED |
| `BACKUP_RESTORE_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `BROWSER_TEST_INVENTORY.md` | Historical report/note | INVENTORIED |
| `BROWSER_TEST_PLAN.md` | Historical report/note | INVENTORIED |
| `BUGS_FIXED_AND_REMAINING.md` | Historical report/note | INVENTORIED |
| `BUG_FIX_REPORT.md` | Historical report/note | HISTORICAL |
| `BUG_REGISTER_AND_FIXES.md` | Historical report/note | INVENTORIED |
| `BUTTON_LINK_AND_FORM_AUDIT.md` | Historical report/note | HISTORICAL |
| `BUTTON_LINK_FORM_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `CHANGELOG.md` | Historical report/note | HISTORICAL |
| `CODEBASE_AUDIT.md` | Historical report/note | HISTORICAL |
| `CODEX_BLOCKERS_AND_UNVERIFIED_CLAIMS.md` | Historical report/note | INVENTORIED |
| `CODEX_DIAGNOSTIC_SCRIPTS.md` | Historical report/note | INVENTORIED |
| `CODEX_FINAL_VERIFICATION_REPORT.md` | Historical report/note | HISTORICAL |
| `CODEX_SECURITY_TENANT_ISOLATION_REPORT.md` | Historical report/note | HISTORICAL |
| `CODEX_SEO_BUILD_QA_REPORT.md` | Historical report/note | HISTORICAL |
| `CODEX_THEME_REDESIGN_REPORT.md` | Historical report/note | HISTORICAL |
| `COMPLETE_BROWSER_QA_REPORT.md` | Historical report/note | HISTORICAL |
| `COMPLETE_ENTERPRISE_AUDIT.md` | Historical report/note | HISTORICAL |
| `COMPLETE_SOURCE_CODE_AUDIT.md` | Historical report/note | HISTORICAL |
| `CRITICAL_SECURITY_FINDINGS.md` | Historical report/note | INVENTORIED |
| `CRITICAL_SECURITY_FIX_REPORT.md` | Historical report/note | HISTORICAL |
| `DATABASE_ERD.md` | Historical report/note | INVENTORIED |
| `DATABASE_ERD_DATA_DICTIONARY.md` | Historical report/note | INVENTORIED |
| `DATABASE_MIGRATION_AUDIT.md` | Historical report/note | HISTORICAL |
| `DATABASE_SCHEMA.md` | Historical report/note | INVENTORIED |
| `DEMO_START_GUIDE.md` | Historical report/note | INVENTORIED |
| `DEPLOYMENT.md` | Historical report/note | INVENTORIED |
| `DEPLOYMENT_AND_CICD_AUDIT.md` | Historical report/note | HISTORICAL |
| `DEPLOYMENT_GUIDE.md` | Historical report/note | INVENTORIED |
| `DEVELOPMENT_MASTER_PROMPT.md` | Historical report/note | INVENTORIED |
| `Dockerfile.prod` | Legacy multi-stage image — DEPRECATED, reviewed and retired | REVIEWED |
| `END_TO_END_WORKFLOW_REPORT.md` | Historical report/note | HISTORICAL |
| `ENTERPRISE_APPLICATION_INVENTORY.md` | Historical report/note | INVENTORIED |
| `ENTERPRISE_FEATURE_GAP_ANALYSIS.md` | Historical report/note | INVENTORIED |
| `ENTERPRISE_MARKETPLACE_V2_REPORT.md` | Historical report/note | HISTORICAL |
| `ENTERPRISE_REMEDIATION_REPORT.md` | Historical report/note | HISTORICAL |
| `ENTERPRISE_TRANSFORMATION_REPORT.md` | Historical report/note | HISTORICAL |
| `FILE_AUDIT_LEDGER.md` | Historical report/note | INVENTORIED |
| `FILE_STORAGE_DEPLOYMENT_GUIDE.md` | Historical report/note | INVENTORIED |
| `FILE_STORAGE_SECURITY_REPORT.md` | Historical report/note | HISTORICAL |
| `FILE_STORAGE_THREAT_MODEL.md` | Historical report/note | INVENTORIED |
| `FINAL_100_PERCENT_COMPLETION_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_BROWSER_E2E_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_DOCKER_CLAMAV_RUNTIME_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_ENTERPRISE_TRANSFORMATION_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_FINANCE_INTEGRITY_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_INDEPENDENT_AUDIT_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_KILOCODE_VERIFICATION.md` | Historical report/note | INVENTORIED |
| `FINAL_MULTI_TENANT_ISOLATION_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_PERFORMANCE_SEO_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_PRODUCTION_AUDIT.md` | Historical report/note | HISTORICAL |
| `FINAL_PRODUCTION_DEPLOYMENT_CHECKLIST.md` | Historical report/note | HISTORICAL |
| `FINAL_PRODUCTION_READINESS_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_PUBLIC_WEBSITE_DESIGN_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_RELEASE_GATE_REPORT.md` | Historical report/note | HISTORICAL |
| `FINAL_REMAINING_BLOCKERS.md` | Historical report/note | INVENTORIED |
| `FINAL_SECURITY_AUDIT.md` | Historical report/note | HISTORICAL |
| `FINAL_VERIFICATION_REPORT.md` | Historical report/note | HISTORICAL |
| `FINANCE_INTEGRITY_AUDIT.md` | Historical report/note | HISTORICAL |
| `FIREBASE_SETUP.md` | Historical report/note | INVENTORIED |
| `FRONTEND_PAGE_AND_INTERACTION_AUDIT.md` | Historical report/note | HISTORICAL |
| `FRONTEND_UI_UX_AUDIT.md` | Historical report/note | HISTORICAL |
| `HOSTINGER_VPS_DEPLOYMENT.md` | Historical report/note | INVENTORIED |
| `INITIAL_BUG_REGISTER.md` | Historical report/note | INVENTORIED |
| `LOAD_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `MASTER_REQUIREMENTS_GAP_ANALYSIS.md` | Historical report/note | INVENTORIED |
| `MYSQL_PRIMARY_DATABASE.md` | Historical report/note | INVENTORIED |
| `NOVEMBER_25_LAUNCH_CHECKLIST.md` | Historical report/note | HISTORICAL |
| `PAGE_AND_SECTION_AUDIT.md` | Historical report/note | HISTORICAL |
| `PAGE_BY_PAGE_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `PERFORMANCE_SEO_AUDIT.md` | Historical report/note | HISTORICAL |
| `PRODUCTION_CONFIGURATION_AUDIT.md` | Historical report/note | HISTORICAL |
| `PRODUCTION_DEPLOYMENT_CHECKLIST.md` | Historical report/note | HISTORICAL |
| `PRODUCTION_READINESS.md` | Historical report/note | INVENTORIED |
| `PRODUCTION_READINESS_CHECKLIST.md` | Historical report/note | HISTORICAL |
| `PRODUCTION_READINESS_REPORT.md` | Historical report/note | HISTORICAL |
| `PROJECT_ANALYSIS.md` | Historical report/note | INVENTORIED |
| `PROJECT_COMPLETE_AUDIT_REPORT.md` | Historical report/note | HISTORICAL |
| `PROJECT_FINAL_AUDIT_REPORT.md` | Historical report/note | HISTORICAL |
| `PROJECT_IMPLEMENTATION_REPORT.md` | Historical report/note | HISTORICAL |
| `PUBLIC_PAGE_BROWSER_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_BUTTON_LINK_FORM_AUDIT.md` | Historical report/note | HISTORICAL |
| `QODER_COMPLETE_SOURCE_AUDIT.md` | Historical report/note | HISTORICAL |
| `QODER_DATABASE_CONSISTENCY_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_DOCKER_CICD_DEPLOYMENT_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_FILE_VAULT_SECURITY_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_FINAL_PRODUCTION_READINESS_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_PAGE_BY_PAGE_BROWSER_AUDIT.md` | Historical report/note | HISTORICAL |
| `QODER_PERFORMANCE_CORE_WEB_VITALS_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_REDIS_RESILIENCE_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_REMAINING_BLOCKERS.md` | Historical report/note | INVENTORIED |
| `QODER_SCALABILITY_LOAD_BALANCING_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_SECURITY_TENANT_ISOLATION_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_SEO_RESPONSIVE_ACCESSIBILITY_REPORT.md` | Historical report/note | HISTORICAL |
| `QODER_UI_THEME_REGRESSION_REPORT.md` | Historical report/note | HISTORICAL |
| `RATE_LIMIT_PERFORMANCE_REPORT.md` | Historical report/note | HISTORICAL |
| `RBAC_MATRIX.md` | Historical report/note | INVENTORIED |
| `RBAC_PERMISSION_MATRIX.md` | Historical report/note | INVENTORIED |
| `RBAC_TENANT_ISOLATION_REPORT.md` | Historical report/note | HISTORICAL |
| `README.md` | Historical report/note | REVIEWED |
| `REMAINING_BLOCKERS.md` | Historical report/note | INVENTORIED |
| `RESPONSIVE_ACCESSIBILITY_AUDIT.md` | Historical report/note | HISTORICAL |
| `RESPONSIVE_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `ROLE_BASED_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `ROLE_SIMULATION_REPORT.md` | Historical report/note | HISTORICAL |
| `SALE_ONLY_MARKETPLACE_REPORT.md` | Historical report/note | HISTORICAL |
| `SECURE_FILE_VAULT_ARCHITECTURE.md` | Historical report/note | INVENTORIED |
| `SECURE_FILE_VAULT_AUDIT.md` | Historical report/note | HISTORICAL |
| `SECURE_FILE_VAULT_INTEGRATION_REPORT.md` | Historical report/note | HISTORICAL |
| `SECURITY_AND_TENANT_ISOLATION_AUDIT.md` | Historical report/note | HISTORICAL |
| `SECURITY_ARCHITECTURE.md` | Historical report/note | INVENTORIED |
| `SECURITY_AUDIT.md` | Historical report/note | HISTORICAL |
| `SECURITY_AUDIT_REPORT.md` | Historical report/note | HISTORICAL |
| `SECURITY_TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `SECURITY_VERIFICATION_REPORT.md` | Historical report/note | HISTORICAL |
| `TENANT_ISOLATION_AUDIT.md` | Historical report/note | HISTORICAL |
| `TENANT_ISOLATION_VERIFICATION.md` | Historical report/note | INVENTORIED |
| `TESTING_STRATEGY.md` | Historical report/note | INVENTORIED |
| `TEST_REPORT.md` | Historical report/note | HISTORICAL |
| `TEST_RESULTS.md` | Historical report/note | HISTORICAL |
| `TEST_VERIFICATION_REPORT.md` | Historical report/note | HISTORICAL |
| `UI_THEME_REGRESSION_REPORT.md` | Historical report/note | HISTORICAL |
| `UI_UX_RESPONSIVE_AUDIT.md` | Historical report/note | HISTORICAL |
| `UPGRADE_MASTER.md` | Historical report/note | INVENTORIED |
| `UPLOAD_SECURITY_REPORT.md` | Historical report/note | HISTORICAL |
| `WORKFLOW_E2E_REPORT.md` | Historical report/note | HISTORICAL |
| `audit-frontend-verify-A.txt` | Recorded audit output | HISTORICAL |
| `audit-frontend-verify-B.txt` | Recorded audit output | HISTORICAL |
| `audit-phpunit-final.txt` | Recorded audit output | HISTORICAL |
| `audit-phpunit-final2.txt` | Recorded audit output | HISTORICAL |
| `audit-phpunit-final3.txt` | Recorded audit output | HISTORICAL |
| `audit-phpunit-ratelimit-fixed.txt` | Recorded audit output | HISTORICAL |
| `audit-playwright-B.txt` | Recorded audit output | HISTORICAL |
| `audit-playwright-C.txt` | Recorded audit output | HISTORICAL |
| `audit-playwright-D.txt` | Recorded audit output | HISTORICAL |
| `audit-playwright-E.txt` | Recorded audit output | HISTORICAL |
| `audit-run-A.txt` | Recorded audit output | HISTORICAL |
| `backend/.dockerignore` | Manifest/config/asset | INVENTORIED |
| `backend/.editorconfig` | Manifest/config/asset | INVENTORIED |
| `backend/.env.example` | Manifest/config/asset | REVIEWED |
| `backend/.gitattributes` | Manifest/config/asset | INVENTORIED |
| `backend/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/Dockerfile` | Manifest/config/asset | INVENTORIED |
| `backend/Dockerfile.prod` | Manifest/config/asset | REVIEWED |
| `backend/README.md` | Historical report/note | INVENTORIED |
| `backend/app/Console/Commands/BusinessRecordsAuditCommand.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/EncryptVault.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/LegacyBackfillCommand.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/LegacyOwnershipReportCommand.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/PlatformInventoryCommand.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/ProductionCheck.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/PurgeQuarantine.php` | Artisan command | INVENTORIED |
| `backend/app/Console/Commands/SendSavedSearchAlerts.php` | Artisan command | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminAgencyController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminDashboardController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminSystemController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AdminUserController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AgencyController.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/AgencyStaffController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AgencyVideoController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/AuthController.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/AuthorizesApi.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/DealerController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/DealerFilesController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/DocumentController.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/FavoriteController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/FeaturedAgencyController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/FinanceController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/HealthController.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/InquiryController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/LeadController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/LocationController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/MapController.php` | API controller | REVIEWED |
| `backend/app/Http/Controllers/Api/MediaController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/NotificationController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/PaymentController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/ProjectController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/PropertyController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/SavedSearchController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/SubscriptionController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/SupportController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/TransactionController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Api/WorkspaceController.php` | API controller | INVENTORIED |
| `backend/app/Http/Controllers/Controller.php` | API controller | INVENTORIED |
| `backend/app/Http/Middleware/AgencyContext.php` | HTTP middleware | REVIEWED |
| `backend/app/Http/Middleware/CorrelationId.php` | HTTP middleware | INVENTORIED |
| `backend/app/Http/Middleware/EnsureMfaVerified.php` | HTTP middleware | INVENTORIED |
| `backend/app/Http/Middleware/SecurityHeaders.php` | HTTP middleware | INVENTORIED |
| `backend/app/Http/Middleware/TrustProxies.php` | HTTP middleware | INVENTORIED |
| `backend/app/Mail/SavedSearchMatchesMail.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Models/Agency.php` | Eloquent model | REVIEWED |
| `backend/app/Models/AgencyInvitation.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/AgencyMember.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/AgencyPromotion.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/AgencySubscription.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/AgencyVideo.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/AuditLog.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Block.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/BusinessRecord.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Commission.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Dealer.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DealerFile.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Document.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DocumentAccessLog.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DocumentFolder.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DocumentQuarantine.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DocumentShare.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/DocumentVersion.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/EmailVerificationToken.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Expense.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Inquiry.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/LeadActivity.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/LoginHistory.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Map.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/MediaFile.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/PaymentSchedule.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Permission.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Project.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/PromotionPackage.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Property.php` | Eloquent model | REVIEWED |
| `backend/app/Models/PropertyCategory.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/PropertyFavorite.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/PropertyStatusHistory.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/PropertyView.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Role.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SavedSearch.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Sector.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SubscriptionInvoice.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SubscriptionPlan.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SupportMessage.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SupportTicket.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/SystemSetting.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/Transaction.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/TransactionPayment.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/User.php` | Eloquent model | REVIEWED |
| `backend/app/Models/VideoComment.php` | Eloquent model | INVENTORIED |
| `backend/app/Models/VideoLike.php` | Eloquent model | INVENTORIED |
| `backend/app/Notifications/ListingUpdated.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Notifications/PasswordChangedNotification.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Notifications/ResetPasswordLink.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Notifications/SuspiciousLoginDetected.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Notifications/VerifyEmailLink.php` | Manifest/config/asset | INVENTORIED |
| `backend/app/Providers/AppServiceProvider.php` | Service provider | REVIEWED |
| `backend/app/Services/AgencyOnboarding.php` | Service/helper | INVENTORIED |
| `backend/app/Services/AgencyPromotionService.php` | Service/helper | INVENTORIED |
| `backend/app/Services/ClamAVScanner.php` | Service/helper | INVENTORIED |
| `backend/app/Services/DisabledMalwareScanner.php` | Service/helper | INVENTORIED |
| `backend/app/Services/FileVaultCrypto.php` | Service/helper | INVENTORIED |
| `backend/app/Services/MalwareScanner.php` | Service/helper | INVENTORIED |
| `backend/app/Services/SecureFileStore.php` | Service/helper | INVENTORIED |
| `backend/app/Support/LegacyBackfillBackup.php` | Service/helper | INVENTORIED |
| `backend/app/Support/LegacyTenancyResolver.php` | Service/helper | INVENTORIED |
| `backend/app/Support/MalwareScanResult.php` | Service/helper | INVENTORIED |
| `backend/app/Support/MfaService.php` | Service/helper | INVENTORIED |
| `backend/app/Support/MfaVerification.php` | Service/helper | INVENTORIED |
| `backend/app/Support/Permissions.php` | Service/helper | INVENTORIED |
| `backend/app/Support/PlanLimits.php` | Service/helper | INVENTORIED |
| `backend/app/Support/PropertySearchFilters.php` | Service/helper | INVENTORIED |
| `backend/app/Support/RolePermissions.php` | Service/helper | INVENTORIED |
| `backend/app/Support/UploadSecurity.php` | Service/helper | INVENTORIED |
| `backend/artisan` | Manifest/config/asset | INVENTORIED |
| `backend/audit-migrations.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-routes.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-run-B.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-run-C.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-run-D.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-run-E.txt` | Recorded audit output | INVENTORIED |
| `backend/audit-run-F.txt` | Recorded audit output | INVENTORIED |
| `backend/bootstrap/app.php` | Manifest/config/asset | REVIEWED |
| `backend/bootstrap/cache/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/bootstrap/providers.php` | Manifest/config/asset | INVENTORIED |
| `backend/composer.json` | Manifest/config/asset | REVIEWED |
| `backend/composer.lock` | Manifest/config/asset | REVIEWED |
| `backend/config/app.php` | Laravel configuration | INVENTORIED |
| `backend/config/auth.php` | Laravel configuration | INVENTORIED |
| `backend/config/cache.php` | Laravel configuration | INVENTORIED |
| `backend/config/cors.php` | Laravel configuration | REVIEWED |
| `backend/config/database.php` | Laravel configuration | REVIEWED |
| `backend/config/filesystems.php` | Laravel configuration | INVENTORIED |
| `backend/config/hashing.php` | Laravel configuration | INVENTORIED |
| `backend/config/logging.php` | Laravel configuration | INVENTORIED |
| `backend/config/mail.php` | Laravel configuration | INVENTORIED |
| `backend/config/malware.php` | Laravel configuration | INVENTORIED |
| `backend/config/queue.php` | Laravel configuration | REVIEWED |
| `backend/config/sanctum.php` | Laravel configuration | INVENTORIED |
| `backend/config/services.php` | Laravel configuration | INVENTORIED |
| `backend/config/session.php` | Laravel configuration | INVENTORIED |
| `backend/config/vault.php` | Laravel configuration | INVENTORIED |
| `backend/database/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/database/factories/UserFactory.php` | Seeder/factory | INVENTORIED |
| `backend/database/init.sql` | Manifest/config/asset | INVENTORIED |
| `backend/database/migrations/0001_01_01_000000_create_users_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/0001_01_01_000001_create_cache_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/0001_01_01_000002_create_jobs_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145235_create_personal_access_tokens_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145311_create_dealers_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145312_create_properties_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145313_create_agency_videos_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145315_create_video_comments_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145316_create_inquiries_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145318_create_transactions_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_02_145319_create_projects_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_100000_create_dealer_files.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_110000_add_role_to_users.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_130000_add_soft_deletes_to_remaining_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_160000_create_audit_logs_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_03_170000_create_business_records_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_05_100000_add_indexes_to_business_records.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_05_110000_add_extra_fields_to_properties.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000001_create_agencies_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000002_create_rbac_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000003_create_agency_members_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000004_add_enterprise_fields_to_users.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000005_create_location_and_category_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000006_add_workflow_fields_to_properties.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000007_create_property_workflow_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000008_create_document_vault_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000009_create_media_files_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000010_add_crm_fields_to_inquiries.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000011_create_finance_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000012_create_notifications_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000013_create_subscription_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000014_create_system_settings_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000015_extend_audit_logs_and_business_records.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000016_backfill_enterprise_relations.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000017_add_full_fields_to_properties.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000018_add_pending_changes_to_properties.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000019_add_video_engagement_attribution.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_10_000020_add_finance_detail_columns_to_transactions.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_13_000001_encrypt_dealer_cnic_at_rest.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_13_000002_create_email_verification_tokens.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_13_000003_create_support_expenses_invoices_shares_tables.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_13_000004_add_mfa_and_login_history.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_15_142734_widen_dealer_files_decimal_fields.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_15_184000_make_property_id_nullable_in_transactions.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_16_000001_create_saved_searches_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_17_000001_add_vault_scan_lifecycle.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_20_000001_add_billing_lifecycle_columns.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_20_000002_create_featured_agency_promotions.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_20_092340_add_google_oauth_to_users_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php` | Schema migration | REVIEWED |
| `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php` | Schema migration | REVIEWED |
| `backend/database/migrations/2026_11_23_000001_create_maps_table.php` | Schema migration | TEST-COVERED (fresh MySQL migration) |
| `backend/database/seeders/AgencyFilesSyncSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/database/seeders/DatabaseSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/database/seeders/DemoStaffSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/database/seeders/EnterpriseDemoSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/database/seeders/EnterpriseSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/database/seeders/MapSeeder.php` | Seeder/factory | INVENTORIED |
| `backend/docker/entrypoint.sh` | Manifest/config/asset | REVIEWED |
| `backend/docker/php-prod.ini` | Manifest/config/asset | INVENTORIED |
| `backend/package.json` | Manifest/config/asset | INVENTORIED |
| `backend/phpunit.xml` | Manifest/config/asset | REVIEWED |
| `backend/public/.htaccess` | Manifest/config/asset | INVENTORIED |
| `backend/public/favicon.ico` | Manifest/config/asset | ASSET (inventory only) |
| `backend/public/index.php` | Manifest/config/asset | INVENTORIED |
| `backend/public/robots.txt` | Recorded audit output | INVENTORIED |
| `backend/resources/css/app.css` | Backend resource/template | INVENTORIED |
| `backend/resources/js/app.js` | Backend resource/template | INVENTORIED |
| `backend/resources/js/bootstrap.js` | Backend resource/template | INVENTORIED |
| `backend/resources/views/emails/saved-search-matches-text.blade.php` | Backend resource/template | INVENTORIED |
| `backend/resources/views/emails/saved-search-matches.blade.php` | Backend resource/template | INVENTORIED |
| `backend/resources/views/welcome.blade.php` | Backend resource/template | INVENTORIED |
| `backend/route-method-audit.php` | Manifest/config/asset | INVENTORIED |
| `backend/routes/api.php` | Route definition | REVIEWED |
| `backend/routes/api_definitions.php` | Route definition | REVIEWED |
| `backend/routes/console.php` | Route definition | INVENTORIED |
| `backend/routes/web.php` | Route definition | INVENTORIED |
| `backend/scripts/assert-db-driver.php` | Manifest/config/asset | REVIEWED |
| `backend/scripts/healthcheck.php` | Manifest/config/asset | REVIEWED |
| `backend/scripts/wait-for-db.php` | Manifest/config/asset | REVIEWED |
| `backend/storage/app/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/app/private/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/app/private/documents/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/app/public/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/cache/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/cache/data/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/sessions/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/testing/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/framework/views/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/storage/logs/.gitignore` | Manifest/config/asset | INVENTORIED |
| `backend/tests/Feature/AdminStaffSubscriptionTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AgencyContextTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AgencyStorageOversightTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AgencyVideoCommentIsolationTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AgencyVideoPublishingTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/ApiVersioningTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AuthFlowsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/AuthSecurityTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/BookingConcurrencyTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/CatalogShapeTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/CorrelationIdTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/CrmLeadsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/CrossAgencyCapabilitiesTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/DatabaseSeederProductionSafetyTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/DealerAgencyLinkTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/DealerFilesAuthorizationTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/DocumentSharesTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/EncryptionAtRestTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/EnterpriseTestCase.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/ExampleTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/ExpensesTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FavoritesNotificationsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FeaturedAgencyPromotionTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FileVaultSecurityTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FinanceIsolationTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FinanceLifecycleTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/FinanceReportsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/GoogleOAuthTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/LegacyTenancyResolverTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/LegacyTransactionIsolationTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/LoginHistoryTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MalwareScanningTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MalwareUploadEnforcementTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MapManagementSafetyTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MapManagementTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MfaEnforcementTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MfaTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MigrationSmokeTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/MysqlConcurrencyTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/PaymentConfigurationTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/PaymentGatewayTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/PlanLimitEnforcementTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/ProductionReadinessTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/PropertyLifecycleTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/PublicIndexBoundsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/RateLimitTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/SavedSearchAlertsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/SecurityHeadersTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/StorageSecurityTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/SubscriptionInvoicesTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/SupportTicketsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/WorkspaceApprovalTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Feature/WorkspaceHardeningTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Support/concurrency_request.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Support/fake_clamd.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/TestCase.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Unit/ExampleTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Unit/PermissionsCatalogTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/tests/Unit/RolePermissionsTest.php` | Backend test | TEST-COVERED (executed) |
| `backend/vite.config.js` | Manifest/config/asset | INVENTORIED |
| `docker-compose.ci.yml` | Deployment configuration | REVIEWED |
| `docker-compose.yml` | Deployment configuration | REVIEWED |
| `docker/backend/nginx.conf` | Deployment configuration | INVENTORIED |
| `docker/backend/supervisord.conf` | Deployment configuration | INVENTORIED |
| `docker/frontend/nginx.conf` | Deployment configuration | INVENTORIED |
| `docker/nginx.ci.conf` | Deployment configuration | REVIEWED |
| `docs/AUDIT_FILE_COVERAGE.md` | Release-gate/API document | REVIEWED |
| `docs/FINAL_PRODUCTION_AUDIT.md` | Release-gate/API document | REVIEWED |
| `docs/PRODUCTION_RELEASE_CHECKLIST.md` | Release-gate/API document | REVIEWED |
| `docs/openapi.yaml` | Release-gate/API document | INVENTORIED |
| `documents/01_SYSTEM_ARCHITECTURE.md` | Historic specification | HISTORICAL |
| `documents/02_PRD_USER_STORIES.md` | Historic specification | HISTORICAL |
| `documents/03_DATABASE_SCHEMA.md` | Historic specification | HISTORICAL |
| `documents/04_MODEL_ARCHITECTURE.md` | Historic specification | HISTORICAL |
| `documents/05_SEEDERS_FACTORIES.md` | Historic specification | HISTORICAL |
| `documents/06_SERVICE_LAYER.md` | Historic specification | HISTORICAL |
| `documents/07_VALIDATION_SANITIZATION.md` | Historic specification | HISTORICAL |
| `documents/08_AUTH_RBAC.md` | Historic specification | HISTORICAL |
| `documents/09_CRUD_CONTROLLERS_API.md` | Historic specification | HISTORICAL |
| `documents/10_RESPONSIVE_UI.md` | Historic specification | HISTORICAL |
| `documents/11_LIVE_INTERACTIVE_STATES.md` | Historic specification | HISTORICAL |
| `documents/12_TOAST_NOTIFICATIONS.md` | Historic specification | HISTORICAL |
| `documents/13_SECURITY_HARDENING.md` | Historic specification | HISTORICAL |
| `documents/14_ERROR_HANDLING_LOGGING.md` | Historic specification | HISTORICAL |
| `documents/15_DEPLOYMENT_CICD.md` | Historic specification | HISTORICAL |
| `documents/16_ENTERPRISE_ARCHITECTURE.md` | Historic specification | HISTORICAL |
| `frontend/.dockerignore` | Manifest/config/asset | INVENTORIED |
| `frontend/.env.example` | Manifest/config/asset | REVIEWED |
| `frontend/.gitignore` | Manifest/config/asset | INVENTORIED |
| `frontend/.screenshots/audit/agencies-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/agency-not-found-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/final-home-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/final-home-mobile.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/final-properties-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/gallery-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/home-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/home-mobile.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/login-mobile.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/not-found-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/.screenshots/audit/properties-desktop.png` | Manifest/config/asset | ASSET (inventory only) |
| `frontend/Dockerfile` | Manifest/config/asset | INVENTORIED |
| `frontend/Dockerfile.prod` | Manifest/config/asset | REVIEWED |
| `frontend/__seo.mjs` | Manifest/config/asset | INVENTORIED |
| `frontend/audit-verify-C.txt` | Recorded audit output | INVENTORIED |
| `frontend/audit-verify-D.txt` | Recorded audit output | INVENTORIED |
| `frontend/e2e/admin-pages.spec.ts` | Playwright E2E test/helper | REVIEWED |
| `frontend/e2e/agency-marketplace.smoke.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/helpers/adminLogin.ts` | Playwright E2E test/helper | REVIEWED |
| `frontend/e2e/helpers/isolatedIp.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/interactive-elements.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/maps.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/public-pages.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/responsive.spec.ts` | Playwright E2E test/helper | REVIEWED |
| `frontend/e2e/role-based.spec.ts` | Playwright E2E test/helper | REVIEWED |
| `frontend/e2e/sale-only.smoke.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/security.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/smoke.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/e2e/workflows.spec.ts` | Playwright E2E test/helper | TEST-COVERED (executed) |
| `frontend/final-frontend-verify.txt` | Recorded audit output | INVENTORIED |
| `frontend/final-playwright.txt` | Recorded audit output | INVENTORIED |
| `frontend/final-playwright2.txt` | Recorded audit output | INVENTORIED |
| `frontend/index.html` | Manifest/config/asset | INVENTORIED |
| `frontend/nginx.conf` | Manifest/config/asset | REVIEWED |
| `frontend/package-lock.json` | Manifest/config/asset | INVENTORIED |
| `frontend/package.json` | Manifest/config/asset | REVIEWED |
| `frontend/playwright.config.ts` | Manifest/config/asset | REVIEWED |
| `frontend/public/favicon.svg` | Static public asset | ASSET (inventory only) |
| `frontend/public/hero-bg.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/hero-slide-2.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/hero-slide-3.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/hero-slide-4.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/hero-slide-5.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/image.png` | Static public asset | ASSET (inventory only) |
| `frontend/public/logo.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/manifest.webmanifest` | Static public asset | INVENTORIED |
| `frontend/public/og-image.jpg` | Static public asset | ASSET (inventory only) |
| `frontend/public/robots.txt` | Static public asset | INVENTORIED |
| `frontend/public/sample-video.mp4` | Static public asset | INVENTORIED |
| `frontend/public/sw.js` | Static public asset | INVENTORIED |
| `frontend/scripts/_audit.mjs` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/browser-admin-interactions.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/browser-audit.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/browser-interactions.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/check-bundle.mjs` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/concurrency-booking.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/legacy-visibility-probe.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/load-test.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/mail-flow-e2e.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/mail-sink.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/mfa-e2e.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/perf-snapshot.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/prerender.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/qa-audit.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/qa-contact.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/qa-theme.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/role-matrix-e2e.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/run-tests.mjs` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/security-probe.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/sensitive-strings.json` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/shot-current.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/shot-mobile-parts.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/verify-fixes.mts` | Frontend build/test script | INVENTORIED |
| `frontend/scripts/workflows-e2e.mts` | Frontend build/test script | INVENTORIED |
| `frontend/src/App.tsx` | Manifest/config/asset | INVENTORIED |
| `frontend/src/components/admin/AdminAgencyApprovals.test.ts` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminAgencyApprovals.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminAgencyDashboard.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminAgencyOversight.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminAudit.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminBuyers.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminCMS.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminCrmPipeline.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminDealers.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminDocumentVault.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminEnterpriseUi.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminFeaturedPromotions.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminFileManagement.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminInquiries.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminLayout.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminLocations.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminLoginModal.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminMapManagement.test.ts` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminMapManagement.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminMediaLibrary.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminMediaStudio.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminMfaSettings.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminModerationDesk.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminModuleSkeleton.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminNotifications.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminOverview.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminPlotLocator.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminProjects.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminProperties.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminSecurityRoles.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminSellers.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminStaffManager.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminSubscription.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminSupportDesk.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/AdminTransactions.tsx` | React component | INVENTORIED |
| `frontend/src/components/admin/adminFileUpload.test.ts` | React component | INVENTORIED |
| `frontend/src/components/common/AgencyDetailsModal.test.ts` | React component | INVENTORIED |
| `frontend/src/components/common/AgencyDetailsModal.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/ConfirmDialog.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/EmptyState.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/ErrorBoundary.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/FavoriteHeart.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/Footer.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/ImageWithFallback.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/Lightbox.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/MfaChallengeForm.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/Navbar.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/Reveal.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/StatsCounter.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/ToastContainer.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/VideoModal.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/WhatsAppFloatingBtn.tsx` | React component | INVENTORIED |
| `frontend/src/components/common/statusBadges.test.ts` | React component | INVENTORIED |
| `frontend/src/components/common/usePrefersReducedMotion.ts` | React component | INVENTORIED |
| `frontend/src/components/presentation/DeviceFrame.tsx` | React component | INVENTORIED |
| `frontend/src/components/presentation/PresentationBar.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AboutSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AgenciesDirectoryPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AgenciesSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AgencyAccessPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AgencyProfilePage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AuthPage.test.ts` | React component | INVENTORIED |
| `frontend/src/components/public/AuthPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/AvailableForSaleSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/CompareModal.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/CompareTray.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/ContactSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/CrawlableDetailLinks.test.ts` | React component | INVENTORIED |
| `frontend/src/components/public/FeaturedProjects.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/FeaturedProperties.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/HeroSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/MapsPage.test.ts` | React component | INVENTORIED |
| `frontend/src/components/public/MapsPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/MediaGallerySection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/NotFoundPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/ProjectDetailPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/ProjectsListingPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/PropertiesListingPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/PropertyDetailPage.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/SaveSearchModal.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/SavedSearchPanel.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/Scheme45Guide.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/ServicesSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/TestimonialsSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/VideoWalkthroughSection.tsx` | React component | INVENTORIED |
| `frontend/src/components/public/heroSlider.test.ts` | React component | INVENTORIED |
| `frontend/src/components/public/mediaVideoFlow.test.ts` | React component | INVENTORIED |
| `frontend/src/config/appMode.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/config/appMode.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/config/seo.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/context/AppContext.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/context/AppContext.tsx` | Frontend domain/state | INVENTORIED |
| `frontend/src/data/agencyVideosSeed.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/data/erpSeed.empty.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/data/erpSeed.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/data/publicSeed.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/domain/auditLog.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/auditLog.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/authSession.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/mediaPolicy.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/mediaPolicy.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/mfa.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/mfa.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/permissions.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/permissions.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/transactions.test.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/domain/transactions.ts` | Frontend domain/state | INVENTORIED |
| `frontend/src/index.css` | Manifest/config/asset | INVENTORIED |
| `frontend/src/main.tsx` | Manifest/config/asset | INVENTORIED |
| `frontend/src/pages/AuthFlowPages.tsx` | React page/route | INVENTORIED |
| `frontend/src/services/api.ts` | API client/service | INVENTORIED |
| `frontend/src/storage/indexedDb.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/types/index.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/apiProperty.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/apiProperty.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/documentUpload.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/documentUpload.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/exportCsv.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/exportCsv.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/logic.sales.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/logic.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/logic.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/marketplaceFeatures.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/propertyCompare.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/reloadGuard.test.ts` | Frontend unit test | INVENTORIED |
| `frontend/src/utils/reloadGuard.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/src/utils/savedSearchFilters.ts` | Manifest/config/asset | INVENTORIED |
| `frontend/tsc-appfix.txt` | Recorded audit output | INVENTORIED |
| `frontend/tsconfig.json` | Manifest/config/asset | INVENTORIED |
| `frontend/vercel.json` | Manifest/config/asset | INVENTORIED |
| `frontend/vite.config.ts` | Manifest/config/asset | INVENTORIED |
| `metadata.json` | Manifest/config/asset | INVENTORIED |
| `nginx/nginx.conf` | Deployment configuration | REVIEWED |
| `package.json` | Manifest/config/asset | REVIEWED |
| `scripts/backup.ps1` | Ops script | REVIEWED |
| `scripts/backup.sh` | Ops script | REVIEWED |
| `scripts/restore-drill.ps1` | Ops script | REVIEWED |
| `scripts/wait-for-compose-ready.sh` | Ops script | REVIEWED |
| `vercel.json` | Manifest/config/asset | INVENTORIED |
