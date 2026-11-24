# File Ledger — Remaining Work Completion Mission

Generated from `git ls-files` at HEAD `0f01512`: **742 tracked files**. Statuses are conservative and reflect actual review/execution in this and the immediately preceding verified mission. Inventory alone is never marked as review.

| Status | Count | Meaning |
|---|---:|---|
| READ | 33 | File contents actually read by the auditor |
| REVIEWED | 0 | Purpose, behaviour and risks understood |
| TESTED | 198 | Exercised by an executed test or browser interaction |
| VERIFIED | 3 | Independent evidence confirmed behaviour/fix |
| NOT REVIEWED | 508 | Inventoried only; no review claim |
| BLOCKED | 0 | Cannot be verified locally (external items in report) |

## Area breakdown

| Area | Files |
|---|---:|
| `(root)` | 156 |
| `frontend/src` | 131 |
| `backend/app` | 117 |
| `backend/database` | 63 |
| `backend/tests` | 61 |
| `frontend/scripts` | 24 |
| `AUDIT_EVIDENCE` | 18 |
| `docs` | 16 |
| `documents` | 16 |
| `backend/config` | 15 |
| `frontend/e2e` | 14 |
| `frontend/public` | 13 |
| `backend/storage` | 11 |
| `frontend/.screenshots` | 11 |
| `backend/resources` | 6 |
| `backend/public` | 4 |
| `backend/routes` | 4 |
| `docker` | 4 |
| `scripts` | 4 |
| `backend/bootstrap` | 3 |
| `backend/scripts` | 3 |
| `.github` | 2 |
| `backend/docker` | 2 |
| `.screenshots` | 1 |
| `backend/.dockerignore` | 1 |
| `backend/.editorconfig` | 1 |
| `backend/.env.example` | 1 |
| `backend/.gitattributes` | 1 |
| `backend/.gitignore` | 1 |
| `backend/Dockerfile` | 1 |
| `backend/Dockerfile.prod` | 1 |
| `backend/README.md` | 1 |
| `backend/artisan` | 1 |
| `backend/audit-migrations.txt` | 1 |
| `backend/audit-routes.txt` | 1 |
| `backend/audit-run-B.txt` | 1 |
| `backend/audit-run-C.txt` | 1 |
| `backend/audit-run-D.txt` | 1 |
| `backend/audit-run-E.txt` | 1 |
| `backend/audit-run-F.txt` | 1 |
| `backend/composer.json` | 1 |
| `backend/composer.lock` | 1 |
| `backend/package.json` | 1 |
| `backend/phpunit.xml` | 1 |
| `backend/route-method-audit.php` | 1 |
| `backend/vite.config.js` | 1 |
| `frontend/.dockerignore` | 1 |
| `frontend/.env.example` | 1 |
| `frontend/.gitignore` | 1 |
| `frontend/Dockerfile` | 1 |
| `frontend/Dockerfile.prod` | 1 |
| `frontend/__seo.mjs` | 1 |
| `frontend/audit-verify-C.txt` | 1 |
| `frontend/audit-verify-D.txt` | 1 |
| `frontend/final-frontend-verify.txt` | 1 |
| `frontend/final-playwright.txt` | 1 |
| `frontend/final-playwright2.txt` | 1 |
| `frontend/index.html` | 1 |
| `frontend/nginx.conf` | 1 |
| `frontend/package-lock.json` | 1 |
| `frontend/package.json` | 1 |
| `frontend/playwright.config.ts` | 1 |
| `frontend/tsc-appfix.txt` | 1 |
| `frontend/tsconfig.json` | 1 |
| `frontend/vercel.json` | 1 |
| `frontend/vite.config.ts` | 1 |
| `nginx` | 1 |

## Per-file ledger

| Path | Area | Status |
|---|---|---|
| `.github/workflows/ci.yml` | .github | NOT REVIEWED |
| `.github/workflows/docker-ci.yml` | .github | NOT REVIEWED |
| `.gitignore` | (root) | NOT REVIEWED |
| `.nvmrc` | (root) | NOT REVIEWED |
| `.screenshots/api-properties.json` | .screenshots | NOT REVIEWED |
| `AGENCY_MARKETPLACE_IMPLEMENTATION_REPORT.md` | (root) | NOT REVIEWED |
| `API_DATABASE_VERIFICATION_REPORT.md` | (root) | NOT REVIEWED |
| `API_DOCS.md` | (root) | NOT REVIEWED |
| `API_DOCUMENTATION.md` | (root) | NOT REVIEWED |
| `API_ROUTE_AND_AUTHORIZATION_AUDIT.md` | (root) | NOT REVIEWED |
| `AUDIT_EVIDENCE/01-home.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/02-properties.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/03-property-detail.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/04-agencies.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/05-contact.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/06-mobile-menu.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/07-responsive-1440.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/07-responsive-1920.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/07-responsive-320.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/07-responsive-768.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/08-login.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/09-admin-guard.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/10-properties-search.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/11-property-detail.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/12-contact-submit.png` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/lighthouse-homepage-final.json` | AUDIT_EVIDENCE | VERIFIED |
| `AUDIT_EVIDENCE/sweep-results.json` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_EVIDENCE/sweep2-results.json` | AUDIT_EVIDENCE | NOT REVIEWED |
| `AUDIT_PROGRESS.md` | (root) | READ |
| `AUDIT_REPORT.md` | (root) | NOT REVIEWED |
| `BACKEND_DATABASE_API_AUDIT.md` | (root) | NOT REVIEWED |
| `BACKUP_GUIDE.md` | (root) | NOT REVIEWED |
| `BACKUP_RESTORE_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `BROWSER_TEST_INVENTORY.md` | (root) | NOT REVIEWED |
| `BROWSER_TEST_PLAN.md` | (root) | NOT REVIEWED |
| `BUGS_FIXED_AND_REMAINING.md` | (root) | NOT REVIEWED |
| `BUG_FIX_REPORT.md` | (root) | NOT REVIEWED |
| `BUG_REGISTER_AND_FIXES.md` | (root) | NOT REVIEWED |
| `BUTTON_LINK_AND_FORM_AUDIT.md` | (root) | NOT REVIEWED |
| `BUTTON_LINK_FORM_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `CHANGELOG.md` | (root) | NOT REVIEWED |
| `CODEBASE_AUDIT.md` | (root) | NOT REVIEWED |
| `CODEX_BLOCKERS_AND_UNVERIFIED_CLAIMS.md` | (root) | NOT REVIEWED |
| `CODEX_DIAGNOSTIC_SCRIPTS.md` | (root) | NOT REVIEWED |
| `CODEX_FINAL_VERIFICATION_REPORT.md` | (root) | NOT REVIEWED |
| `CODEX_SECURITY_TENANT_ISOLATION_REPORT.md` | (root) | NOT REVIEWED |
| `CODEX_SEO_BUILD_QA_REPORT.md` | (root) | NOT REVIEWED |
| `CODEX_THEME_REDESIGN_REPORT.md` | (root) | NOT REVIEWED |
| `COMPLETE_BROWSER_QA_REPORT.md` | (root) | NOT REVIEWED |
| `COMPLETE_ENTERPRISE_AUDIT.md` | (root) | NOT REVIEWED |
| `COMPLETE_SOURCE_CODE_AUDIT.md` | (root) | NOT REVIEWED |
| `CRITICAL_SECURITY_FINDINGS.md` | (root) | NOT REVIEWED |
| `CRITICAL_SECURITY_FIX_REPORT.md` | (root) | NOT REVIEWED |
| `DATABASE_ERD.md` | (root) | NOT REVIEWED |
| `DATABASE_ERD_DATA_DICTIONARY.md` | (root) | NOT REVIEWED |
| `DATABASE_MIGRATION_AUDIT.md` | (root) | NOT REVIEWED |
| `DATABASE_SCHEMA.md` | (root) | NOT REVIEWED |
| `DEMO_START_GUIDE.md` | (root) | NOT REVIEWED |
| `DEPLOYMENT.md` | (root) | NOT REVIEWED |
| `DEPLOYMENT_AND_CICD_AUDIT.md` | (root) | NOT REVIEWED |
| `DEPLOYMENT_GUIDE.md` | (root) | NOT REVIEWED |
| `DEVELOPMENT_MASTER_PROMPT.md` | (root) | NOT REVIEWED |
| `Dockerfile.prod` | (root) | NOT REVIEWED |
| `END_TO_END_WORKFLOW_REPORT.md` | (root) | NOT REVIEWED |
| `ENTERPRISE_APPLICATION_INVENTORY.md` | (root) | NOT REVIEWED |
| `ENTERPRISE_FEATURE_GAP_ANALYSIS.md` | (root) | NOT REVIEWED |
| `ENTERPRISE_MARKETPLACE_V2_REPORT.md` | (root) | NOT REVIEWED |
| `ENTERPRISE_REMEDIATION_REPORT.md` | (root) | NOT REVIEWED |
| `ENTERPRISE_TRANSFORMATION_REPORT.md` | (root) | NOT REVIEWED |
| `FILE_AUDIT_LEDGER.md` | (root) | NOT REVIEWED |
| `FILE_STORAGE_DEPLOYMENT_GUIDE.md` | (root) | NOT REVIEWED |
| `FILE_STORAGE_SECURITY_REPORT.md` | (root) | NOT REVIEWED |
| `FILE_STORAGE_THREAT_MODEL.md` | (root) | NOT REVIEWED |
| `FINAL_100_PERCENT_COMPLETION_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_BROWSER_E2E_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_CURSOR_AUDIT_AND_REPAIR_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_DOCKER_CLAMAV_RUNTIME_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_ENTERPRISE_TRANSFORMATION_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_FINANCE_INTEGRITY_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_INDEPENDENT_AUDIT_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_KILOCODE_BROWSER_AUDIT_AND_REPAIR_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_KILOCODE_GAP_CLOSURE_AUDIT_REPORT.md` | (root) | READ |
| `FINAL_KILOCODE_VERIFICATION.md` | (root) | NOT REVIEWED |
| `FINAL_MULTI_TENANT_ISOLATION_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_PERFORMANCE_SEO_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_PRODUCTION_AUDIT.md` | (root) | NOT REVIEWED |
| `FINAL_PRODUCTION_DEPLOYMENT_CHECKLIST.md` | (root) | NOT REVIEWED |
| `FINAL_PRODUCTION_READINESS_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_PUBLIC_WEBSITE_DESIGN_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_RELEASE_GATE_REPORT.md` | (root) | NOT REVIEWED |
| `FINAL_REMAINING_BLOCKERS.md` | (root) | NOT REVIEWED |
| `FINAL_SECURITY_AUDIT.md` | (root) | NOT REVIEWED |
| `FINAL_VERIFICATION_REPORT.md` | (root) | NOT REVIEWED |
| `FINANCE_INTEGRITY_AUDIT.md` | (root) | NOT REVIEWED |
| `FIREBASE_SETUP.md` | (root) | NOT REVIEWED |
| `FRONTEND_PAGE_AND_INTERACTION_AUDIT.md` | (root) | NOT REVIEWED |
| `FRONTEND_UI_UX_AUDIT.md` | (root) | NOT REVIEWED |
| `HOSTINGER_VPS_DEPLOYMENT.md` | (root) | NOT REVIEWED |
| `INITIAL_BUG_REGISTER.md` | (root) | NOT REVIEWED |
| `LOAD_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `MASTER_REQUIREMENTS_GAP_ANALYSIS.md` | (root) | NOT REVIEWED |
| `MYSQL_PRIMARY_DATABASE.md` | (root) | NOT REVIEWED |
| `NOVEMBER_25_LAUNCH_CHECKLIST.md` | (root) | NOT REVIEWED |
| `PAGE_AND_SECTION_AUDIT.md` | (root) | NOT REVIEWED |
| `PAGE_BY_PAGE_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `PERFORMANCE_SEO_AUDIT.md` | (root) | NOT REVIEWED |
| `PRODUCTION_CONFIGURATION_AUDIT.md` | (root) | NOT REVIEWED |
| `PRODUCTION_DEPLOYMENT_CHECKLIST.md` | (root) | NOT REVIEWED |
| `PRODUCTION_READINESS.md` | (root) | NOT REVIEWED |
| `PRODUCTION_READINESS_CHECKLIST.md` | (root) | NOT REVIEWED |
| `PRODUCTION_READINESS_REPORT.md` | (root) | NOT REVIEWED |
| `PROJECT_ANALYSIS.md` | (root) | NOT REVIEWED |
| `PROJECT_COMPLETE_AUDIT_REPORT.md` | (root) | NOT REVIEWED |
| `PROJECT_FINAL_AUDIT_REPORT.md` | (root) | NOT REVIEWED |
| `PROJECT_IMPLEMENTATION_REPORT.md` | (root) | NOT REVIEWED |
| `PUBLIC_PAGE_BROWSER_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_BUTTON_LINK_FORM_AUDIT.md` | (root) | NOT REVIEWED |
| `QODER_COMPLETE_SOURCE_AUDIT.md` | (root) | NOT REVIEWED |
| `QODER_DATABASE_CONSISTENCY_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_DOCKER_CICD_DEPLOYMENT_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_FILE_VAULT_SECURITY_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_FINAL_PRODUCTION_READINESS_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_PAGE_BY_PAGE_BROWSER_AUDIT.md` | (root) | NOT REVIEWED |
| `QODER_PERFORMANCE_CORE_WEB_VITALS_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_REDIS_RESILIENCE_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_REMAINING_BLOCKERS.md` | (root) | NOT REVIEWED |
| `QODER_SCALABILITY_LOAD_BALANCING_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_SECURITY_TENANT_ISOLATION_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_SEO_RESPONSIVE_ACCESSIBILITY_REPORT.md` | (root) | NOT REVIEWED |
| `QODER_UI_THEME_REGRESSION_REPORT.md` | (root) | NOT REVIEWED |
| `RATE_LIMIT_PERFORMANCE_REPORT.md` | (root) | NOT REVIEWED |
| `RBAC_MATRIX.md` | (root) | NOT REVIEWED |
| `RBAC_PERMISSION_MATRIX.md` | (root) | NOT REVIEWED |
| `RBAC_TENANT_ISOLATION_REPORT.md` | (root) | NOT REVIEWED |
| `README.md` | (root) | READ |
| `REMAINING_BLOCKERS.md` | (root) | NOT REVIEWED |
| `RESPONSIVE_ACCESSIBILITY_AUDIT.md` | (root) | NOT REVIEWED |
| `RESPONSIVE_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `ROLE_BASED_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `ROLE_SIMULATION_REPORT.md` | (root) | NOT REVIEWED |
| `SALE_ONLY_MARKETPLACE_REPORT.md` | (root) | NOT REVIEWED |
| `SECURE_FILE_VAULT_ARCHITECTURE.md` | (root) | NOT REVIEWED |
| `SECURE_FILE_VAULT_AUDIT.md` | (root) | NOT REVIEWED |
| `SECURE_FILE_VAULT_INTEGRATION_REPORT.md` | (root) | NOT REVIEWED |
| `SECURITY_AND_TENANT_ISOLATION_AUDIT.md` | (root) | NOT REVIEWED |
| `SECURITY_ARCHITECTURE.md` | (root) | NOT REVIEWED |
| `SECURITY_AUDIT.md` | (root) | NOT REVIEWED |
| `SECURITY_AUDIT_REPORT.md` | (root) | NOT REVIEWED |
| `SECURITY_TEST_REPORT.md` | (root) | NOT REVIEWED |
| `SECURITY_VERIFICATION_REPORT.md` | (root) | NOT REVIEWED |
| `TENANT_ISOLATION_AUDIT.md` | (root) | NOT REVIEWED |
| `TENANT_ISOLATION_VERIFICATION.md` | (root) | NOT REVIEWED |
| `TESTING_STRATEGY.md` | (root) | NOT REVIEWED |
| `TEST_REPORT.md` | (root) | NOT REVIEWED |
| `TEST_RESULTS.md` | (root) | NOT REVIEWED |
| `TEST_VERIFICATION_REPORT.md` | (root) | NOT REVIEWED |
| `UI_THEME_REGRESSION_REPORT.md` | (root) | NOT REVIEWED |
| `UI_UX_RESPONSIVE_AUDIT.md` | (root) | NOT REVIEWED |
| `UPGRADE_MASTER.md` | (root) | NOT REVIEWED |
| `UPLOAD_SECURITY_REPORT.md` | (root) | NOT REVIEWED |
| `WORKFLOW_E2E_REPORT.md` | (root) | NOT REVIEWED |
| `audit-frontend-verify-A.txt` | (root) | NOT REVIEWED |
| `audit-frontend-verify-B.txt` | (root) | NOT REVIEWED |
| `audit-phpunit-final.txt` | (root) | NOT REVIEWED |
| `audit-phpunit-final2.txt` | (root) | NOT REVIEWED |
| `audit-phpunit-final3.txt` | (root) | NOT REVIEWED |
| `audit-phpunit-ratelimit-fixed.txt` | (root) | NOT REVIEWED |
| `audit-playwright-B.txt` | (root) | NOT REVIEWED |
| `audit-playwright-C.txt` | (root) | NOT REVIEWED |
| `audit-playwright-D.txt` | (root) | NOT REVIEWED |
| `audit-playwright-E.txt` | (root) | NOT REVIEWED |
| `audit-run-A.txt` | (root) | NOT REVIEWED |
| `backend/.dockerignore` | backend/.dockerignore | NOT REVIEWED |
| `backend/.editorconfig` | backend/.editorconfig | NOT REVIEWED |
| `backend/.env.example` | backend/.env.example | NOT REVIEWED |
| `backend/.gitattributes` | backend/.gitattributes | NOT REVIEWED |
| `backend/.gitignore` | backend/.gitignore | NOT REVIEWED |
| `backend/Dockerfile` | backend/Dockerfile | NOT REVIEWED |
| `backend/Dockerfile.prod` | backend/Dockerfile.prod | NOT REVIEWED |
| `backend/README.md` | backend/README.md | NOT REVIEWED |
| `backend/app/Console/Commands/BusinessRecordsAuditCommand.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/EncryptVault.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/LegacyBackfillCommand.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/LegacyOwnershipReportCommand.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/PlatformInventoryCommand.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/ProductionCheck.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/PurgeQuarantine.php` | backend/app | NOT REVIEWED |
| `backend/app/Console/Commands/SendSavedSearchAlerts.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminAgencyController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminDashboardController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminSystemController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AdminUserController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AgencyController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AgencyStaffController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AgencyVideoController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AuthController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/AuthorizesApi.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/DealerController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/DealerFilesController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/DocumentController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/FavoriteController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/FeaturedAgencyController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/FinanceController.php` | backend/app | READ |
| `backend/app/Http/Controllers/Api/HealthController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/InquiryController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/LeadController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/LocationController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/MapController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/MediaController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/NotificationController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/PaymentController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/ProjectController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/PropertyController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/SavedSearchController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/SubscriptionController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/SupportController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/TransactionController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Api/WorkspaceController.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Controllers/Controller.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Middleware/AgencyContext.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Middleware/CorrelationId.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Middleware/EnsureMfaVerified.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Middleware/SecurityHeaders.php` | backend/app | NOT REVIEWED |
| `backend/app/Http/Middleware/TrustProxies.php` | backend/app | NOT REVIEWED |
| `backend/app/Mail/SavedSearchMatchesMail.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Agency.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AgencyInvitation.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AgencyMember.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AgencyPromotion.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AgencySubscription.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AgencyVideo.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/AuditLog.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Block.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/BusinessRecord.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Commission.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Dealer.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DealerFile.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Document.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DocumentAccessLog.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DocumentFolder.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DocumentQuarantine.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DocumentShare.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/DocumentVersion.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/EmailVerificationToken.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Expense.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Inquiry.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/LeadActivity.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/LoginHistory.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Map.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/MediaFile.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PaymentSchedule.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Permission.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Project.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PromotionPackage.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Property.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PropertyCategory.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PropertyFavorite.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PropertyStatusHistory.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/PropertyView.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Role.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SavedSearch.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Sector.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SubscriptionInvoice.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SubscriptionPlan.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SupportMessage.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SupportTicket.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/SystemSetting.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/Transaction.php` | backend/app | READ |
| `backend/app/Models/TransactionPayment.php` | backend/app | READ |
| `backend/app/Models/User.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/VideoComment.php` | backend/app | NOT REVIEWED |
| `backend/app/Models/VideoLike.php` | backend/app | NOT REVIEWED |
| `backend/app/Notifications/ListingUpdated.php` | backend/app | NOT REVIEWED |
| `backend/app/Notifications/PasswordChangedNotification.php` | backend/app | NOT REVIEWED |
| `backend/app/Notifications/ResetPasswordLink.php` | backend/app | NOT REVIEWED |
| `backend/app/Notifications/SuspiciousLoginDetected.php` | backend/app | NOT REVIEWED |
| `backend/app/Notifications/VerifyEmailLink.php` | backend/app | NOT REVIEWED |
| `backend/app/Providers/AppServiceProvider.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/AgencyOnboarding.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/AgencyPromotionService.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/ClamAVScanner.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/DisabledMalwareScanner.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/FileVaultCrypto.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/MalwareScanner.php` | backend/app | NOT REVIEWED |
| `backend/app/Services/SecureFileStore.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/LegacyBackfillBackup.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/LegacyTenancyResolver.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/MalwareScanResult.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/MfaService.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/MfaVerification.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/Permissions.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/PlanLimits.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/PropertySearchFilters.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/RolePermissions.php` | backend/app | NOT REVIEWED |
| `backend/app/Support/UploadSecurity.php` | backend/app | NOT REVIEWED |
| `backend/artisan` | backend/artisan | NOT REVIEWED |
| `backend/audit-migrations.txt` | backend/audit-migrations.txt | NOT REVIEWED |
| `backend/audit-routes.txt` | backend/audit-routes.txt | NOT REVIEWED |
| `backend/audit-run-B.txt` | backend/audit-run-B.txt | NOT REVIEWED |
| `backend/audit-run-C.txt` | backend/audit-run-C.txt | NOT REVIEWED |
| `backend/audit-run-D.txt` | backend/audit-run-D.txt | NOT REVIEWED |
| `backend/audit-run-E.txt` | backend/audit-run-E.txt | NOT REVIEWED |
| `backend/audit-run-F.txt` | backend/audit-run-F.txt | NOT REVIEWED |
| `backend/bootstrap/app.php` | backend/bootstrap | NOT REVIEWED |
| `backend/bootstrap/cache/.gitignore` | backend/bootstrap | NOT REVIEWED |
| `backend/bootstrap/providers.php` | backend/bootstrap | NOT REVIEWED |
| `backend/composer.json` | backend/composer.json | READ |
| `backend/composer.lock` | backend/composer.lock | NOT REVIEWED |
| `backend/config/app.php` | backend/config | NOT REVIEWED |
| `backend/config/auth.php` | backend/config | NOT REVIEWED |
| `backend/config/cache.php` | backend/config | NOT REVIEWED |
| `backend/config/cors.php` | backend/config | NOT REVIEWED |
| `backend/config/database.php` | backend/config | NOT REVIEWED |
| `backend/config/filesystems.php` | backend/config | NOT REVIEWED |
| `backend/config/hashing.php` | backend/config | NOT REVIEWED |
| `backend/config/logging.php` | backend/config | NOT REVIEWED |
| `backend/config/mail.php` | backend/config | NOT REVIEWED |
| `backend/config/malware.php` | backend/config | NOT REVIEWED |
| `backend/config/queue.php` | backend/config | NOT REVIEWED |
| `backend/config/sanctum.php` | backend/config | NOT REVIEWED |
| `backend/config/services.php` | backend/config | NOT REVIEWED |
| `backend/config/session.php` | backend/config | NOT REVIEWED |
| `backend/config/vault.php` | backend/config | NOT REVIEWED |
| `backend/database/.gitignore` | backend/database | NOT REVIEWED |
| `backend/database/factories/UserFactory.php` | backend/database | NOT REVIEWED |
| `backend/database/init.sql` | backend/database | NOT REVIEWED |
| `backend/database/migrations/0001_01_01_000000_create_users_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/0001_01_01_000001_create_cache_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/0001_01_01_000002_create_jobs_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145235_create_personal_access_tokens_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145311_create_dealers_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145312_create_properties_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145313_create_agency_videos_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145315_create_video_comments_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145316_create_inquiries_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145318_create_transactions_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_02_145319_create_projects_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_100000_create_dealer_files.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_110000_add_role_to_users.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_130000_add_soft_deletes_to_remaining_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_160000_create_audit_logs_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_03_170000_create_business_records_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_05_100000_add_indexes_to_business_records.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_05_110000_add_extra_fields_to_properties.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000001_create_agencies_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000002_create_rbac_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000003_create_agency_members_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000004_add_enterprise_fields_to_users.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000005_create_location_and_category_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000006_add_workflow_fields_to_properties.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000007_create_property_workflow_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000008_create_document_vault_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000009_create_media_files_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000010_add_crm_fields_to_inquiries.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000011_create_finance_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000012_create_notifications_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000013_create_subscription_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000014_create_system_settings_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000015_extend_audit_logs_and_business_records.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000016_backfill_enterprise_relations.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000017_add_full_fields_to_properties.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000018_add_pending_changes_to_properties.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000019_add_video_engagement_attribution.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_10_000020_add_finance_detail_columns_to_transactions.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_13_000001_encrypt_dealer_cnic_at_rest.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_13_000002_create_email_verification_tokens.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_13_000003_create_support_expenses_invoices_shares_tables.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_13_000004_add_mfa_and_login_history.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_15_142734_widen_dealer_files_decimal_fields.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_15_184000_make_property_id_nullable_in_transactions.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_16_000001_create_saved_searches_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_17_000001_add_vault_scan_lifecycle.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_20_000001_add_billing_lifecycle_columns.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_20_000002_create_featured_agency_promotions.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_20_092340_add_google_oauth_to_users_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_23_000001_create_maps_table.php` | backend/database | NOT REVIEWED |
| `backend/database/migrations/2026_11_23_000002_add_deleted_at_to_transaction_payments.php` | backend/database | READ |
| `backend/database/seeders/AgencyFilesSyncSeeder.php` | backend/database | NOT REVIEWED |
| `backend/database/seeders/DatabaseSeeder.php` | backend/database | NOT REVIEWED |
| `backend/database/seeders/DemoStaffSeeder.php` | backend/database | NOT REVIEWED |
| `backend/database/seeders/EnterpriseDemoSeeder.php` | backend/database | NOT REVIEWED |
| `backend/database/seeders/EnterpriseSeeder.php` | backend/database | NOT REVIEWED |
| `backend/database/seeders/MapSeeder.php` | backend/database | NOT REVIEWED |
| `backend/docker/entrypoint.sh` | backend/docker | NOT REVIEWED |
| `backend/docker/php-prod.ini` | backend/docker | NOT REVIEWED |
| `backend/package.json` | backend/package.json | NOT REVIEWED |
| `backend/phpunit.xml` | backend/phpunit.xml | READ |
| `backend/public/.htaccess` | backend/public | NOT REVIEWED |
| `backend/public/favicon.ico` | backend/public | NOT REVIEWED |
| `backend/public/index.php` | backend/public | NOT REVIEWED |
| `backend/public/robots.txt` | backend/public | NOT REVIEWED |
| `backend/resources/css/app.css` | backend/resources | NOT REVIEWED |
| `backend/resources/js/app.js` | backend/resources | NOT REVIEWED |
| `backend/resources/js/bootstrap.js` | backend/resources | NOT REVIEWED |
| `backend/resources/views/emails/saved-search-matches-text.blade.php` | backend/resources | NOT REVIEWED |
| `backend/resources/views/emails/saved-search-matches.blade.php` | backend/resources | NOT REVIEWED |
| `backend/resources/views/welcome.blade.php` | backend/resources | NOT REVIEWED |
| `backend/route-method-audit.php` | backend/route-method-audit.php | NOT REVIEWED |
| `backend/routes/api.php` | backend/routes | READ |
| `backend/routes/api_definitions.php` | backend/routes | READ |
| `backend/routes/console.php` | backend/routes | NOT REVIEWED |
| `backend/routes/web.php` | backend/routes | NOT REVIEWED |
| `backend/scripts/assert-db-driver.php` | backend/scripts | NOT REVIEWED |
| `backend/scripts/healthcheck.php` | backend/scripts | NOT REVIEWED |
| `backend/scripts/wait-for-db.php` | backend/scripts | NOT REVIEWED |
| `backend/storage/app/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/app/private/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/app/private/documents/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/app/public/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/cache/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/cache/data/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/sessions/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/testing/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/framework/views/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/storage/logs/.gitignore` | backend/storage | NOT REVIEWED |
| `backend/tests/Feature/AdminStaffSubscriptionTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AgencyContextTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AgencyStorageOversightTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AgencyVideoCommentIsolationTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AgencyVideoPublishingTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/ApiVersioningTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AuthFlowsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/AuthSecurityTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/BackfillIdempotencyTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/BookingConcurrencyTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/CatalogShapeTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/CorrelationIdTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/CrmLeadsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/CrossAgencyCapabilitiesTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/DatabaseSeederProductionSafetyTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/DealerAgencyLinkTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/DealerFilesAuthorizationTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/DocumentSharesTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/EncryptionAtRestTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/EnterpriseTestCase.php` | backend/tests | TESTED |
| `backend/tests/Feature/ExampleTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/ExpensesTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FavoritesNotificationsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FeaturedAgencyPromotionTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FileVaultSecurityTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FinanceIsolationTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FinanceLifecycleTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/FinanceReportsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/GoogleOAuthTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/LegacyTenancyResolverTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/LegacyTransactionIsolationTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/LoginHistoryTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MalwareScanningTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MalwareUploadEnforcementTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MapManagementSafetyTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MapManagementTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MfaEnforcementTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MfaTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MigrationSmokeTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/MysqlConcurrencyTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/PaymentConfigurationTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/PaymentGatewayTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/PlanLimitEnforcementTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/ProductionReadinessTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/PropertyLifecycleTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/PublicIndexBoundsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/RateLimitTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/SavedSearchAlertsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/SecurityHeadersTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/SensitiveMetadataTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/StorageSecurityTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/SubscriptionInvoicesTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/SupportTicketsTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/WorkspaceApprovalTest.php` | backend/tests | TESTED |
| `backend/tests/Feature/WorkspaceHardeningTest.php` | backend/tests | TESTED |
| `backend/tests/Support/concurrency_request.php` | backend/tests | TESTED |
| `backend/tests/Support/fake_clamd.php` | backend/tests | TESTED |
| `backend/tests/TestCase.php` | backend/tests | TESTED |
| `backend/tests/Unit/ExampleTest.php` | backend/tests | TESTED |
| `backend/tests/Unit/PermissionsCatalogTest.php` | backend/tests | TESTED |
| `backend/tests/Unit/RolePermissionsTest.php` | backend/tests | TESTED |
| `backend/vite.config.js` | backend/vite.config.js | NOT REVIEWED |
| `docker-compose.ci.yml` | (root) | READ |
| `docker-compose.yml` | (root) | READ |
| `docker/backend/nginx.conf` | docker | NOT REVIEWED |
| `docker/backend/supervisord.conf` | docker | NOT REVIEWED |
| `docker/frontend/nginx.conf` | docker | NOT REVIEWED |
| `docker/nginx.ci.conf` | docker | READ |
| `docs/AUDIT_FILE_COVERAGE.md` | docs | NOT REVIEWED |
| `docs/AUDIT_LEDGER_HEADER.md` | docs | NOT REVIEWED |
| `docs/AUDIT_REPAIR_REPORT_2026_11_23.md` | docs | NOT REVIEWED |
| `docs/COMPLETE_FILE_AUDIT_LEDGER.md` | docs | READ |
| `docs/FINAL_ENTERPRISE_AUDIT.md` | docs | NOT REVIEWED |
| `docs/FINAL_INDEPENDENT_AUDIT.md` | docs | READ |
| `docs/FINAL_PRODUCTION_AUDIT.md` | docs | NOT REVIEWED |
| `docs/PERFORMANCE_AUDIT.md` | docs | NOT REVIEWED |
| `docs/PRODUCTION_BLOCKERS.md` | docs | READ |
| `docs/PRODUCTION_READINESS.md` | docs | NOT REVIEWED |
| `docs/PRODUCTION_RELEASE_CHECKLIST.md` | docs | NOT REVIEWED |
| `docs/RELEASE_CHECKLIST.md` | docs | NOT REVIEWED |
| `docs/SECURITY_AUDIT.md` | docs | NOT REVIEWED |
| `docs/TEST_EVIDENCE.md` | docs | READ |
| `docs/VERIFIED_BUGS_AND_REPAIRS.md` | docs | READ |
| `docs/openapi.yaml` | docs | READ |
| `documents/01_SYSTEM_ARCHITECTURE.md` | documents | NOT REVIEWED |
| `documents/02_PRD_USER_STORIES.md` | documents | NOT REVIEWED |
| `documents/03_DATABASE_SCHEMA.md` | documents | NOT REVIEWED |
| `documents/04_MODEL_ARCHITECTURE.md` | documents | NOT REVIEWED |
| `documents/05_SEEDERS_FACTORIES.md` | documents | NOT REVIEWED |
| `documents/06_SERVICE_LAYER.md` | documents | NOT REVIEWED |
| `documents/07_VALIDATION_SANITIZATION.md` | documents | NOT REVIEWED |
| `documents/08_AUTH_RBAC.md` | documents | NOT REVIEWED |
| `documents/09_CRUD_CONTROLLERS_API.md` | documents | NOT REVIEWED |
| `documents/10_RESPONSIVE_UI.md` | documents | NOT REVIEWED |
| `documents/11_LIVE_INTERACTIVE_STATES.md` | documents | NOT REVIEWED |
| `documents/12_TOAST_NOTIFICATIONS.md` | documents | NOT REVIEWED |
| `documents/13_SECURITY_HARDENING.md` | documents | NOT REVIEWED |
| `documents/14_ERROR_HANDLING_LOGGING.md` | documents | NOT REVIEWED |
| `documents/15_DEPLOYMENT_CICD.md` | documents | NOT REVIEWED |
| `documents/16_ENTERPRISE_ARCHITECTURE.md` | documents | NOT REVIEWED |
| `frontend/.dockerignore` | frontend/.dockerignore | NOT REVIEWED |
| `frontend/.env.example` | frontend/.env.example | NOT REVIEWED |
| `frontend/.gitignore` | frontend/.gitignore | NOT REVIEWED |
| `frontend/.screenshots/audit/agencies-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/agency-not-found-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/final-home-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/final-home-mobile.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/final-properties-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/gallery-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/home-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/home-mobile.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/login-mobile.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/not-found-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/.screenshots/audit/properties-desktop.png` | frontend/.screenshots | NOT REVIEWED |
| `frontend/Dockerfile` | frontend/Dockerfile | NOT REVIEWED |
| `frontend/Dockerfile.prod` | frontend/Dockerfile.prod | NOT REVIEWED |
| `frontend/__seo.mjs` | frontend/__seo.mjs | NOT REVIEWED |
| `frontend/audit-verify-C.txt` | frontend/audit-verify-C.txt | NOT REVIEWED |
| `frontend/audit-verify-D.txt` | frontend/audit-verify-D.txt | NOT REVIEWED |
| `frontend/e2e/admin-pages.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/agency-marketplace.smoke.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/helpers/adminLogin.ts` | frontend/e2e | TESTED |
| `frontend/e2e/helpers/isolatedIp.ts` | frontend/e2e | TESTED |
| `frontend/e2e/interactive-elements.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/maps.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/public-pages.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/responsive.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/role-based.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/sale-only.smoke.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/security.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/smoke.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/whatsapp.spec.ts` | frontend/e2e | TESTED |
| `frontend/e2e/workflows.spec.ts` | frontend/e2e | TESTED |
| `frontend/final-frontend-verify.txt` | frontend/final-frontend-verify.txt | NOT REVIEWED |
| `frontend/final-playwright.txt` | frontend/final-playwright.txt | NOT REVIEWED |
| `frontend/final-playwright2.txt` | frontend/final-playwright2.txt | NOT REVIEWED |
| `frontend/index.html` | frontend/index.html | NOT REVIEWED |
| `frontend/nginx.conf` | frontend/nginx.conf | NOT REVIEWED |
| `frontend/package-lock.json` | frontend/package-lock.json | NOT REVIEWED |
| `frontend/package.json` | frontend/package.json | READ |
| `frontend/playwright.config.ts` | frontend/playwright.config.ts | READ |
| `frontend/public/favicon.svg` | frontend/public | NOT REVIEWED |
| `frontend/public/hero-bg.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/hero-slide-2.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/hero-slide-3.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/hero-slide-4.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/hero-slide-5.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/image.png` | frontend/public | NOT REVIEWED |
| `frontend/public/logo.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/manifest.webmanifest` | frontend/public | NOT REVIEWED |
| `frontend/public/og-image.jpg` | frontend/public | NOT REVIEWED |
| `frontend/public/robots.txt` | frontend/public | NOT REVIEWED |
| `frontend/public/sample-video.mp4` | frontend/public | NOT REVIEWED |
| `frontend/public/sw.js` | frontend/public | NOT REVIEWED |
| `frontend/scripts/_audit.mjs` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/browser-admin-interactions.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/browser-audit.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/browser-interactions.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/check-bundle.mjs` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/concurrency-booking.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/legacy-visibility-probe.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/load-test.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/mail-flow-e2e.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/mail-sink.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/mfa-e2e.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/perf-snapshot.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/prerender.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/qa-audit.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/qa-contact.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/qa-theme.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/role-matrix-e2e.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/run-tests.mjs` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/security-probe.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/sensitive-strings.json` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/shot-current.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/shot-mobile-parts.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/verify-fixes.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/scripts/workflows-e2e.mts` | frontend/scripts | NOT REVIEWED |
| `frontend/src/App.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminAgencyApprovals.test.ts` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminAgencyApprovals.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminAgencyDashboard.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminAgencyOversight.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminAudit.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminBuyers.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminCMS.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminCrmPipeline.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminDealers.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminDocumentVault.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminEnterpriseUi.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminFeaturedPromotions.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminFileManagement.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminInquiries.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminLayout.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminLocations.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminLoginModal.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminMapManagement.test.ts` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminMapManagement.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminMediaLibrary.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminMediaStudio.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminMfaSettings.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminModerationDesk.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminModuleSkeleton.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminNotifications.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminOverview.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminPlotLocator.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminProjects.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminProperties.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminSecurityRoles.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminSellers.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminStaffManager.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminSubscription.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminSupportDesk.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/AdminTransactions.tsx` | frontend/src | TESTED |
| `frontend/src/components/admin/adminFileUpload.test.ts` | frontend/src | TESTED |
| `frontend/src/components/common/AgencyDetailsModal.test.ts` | frontend/src | TESTED |
| `frontend/src/components/common/AgencyDetailsModal.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/ConfirmDialog.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/EmptyState.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/ErrorBoundary.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/FavoriteHeart.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/Footer.tsx` | frontend/src | VERIFIED |
| `frontend/src/components/common/ImageWithFallback.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/Lightbox.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/MfaChallengeForm.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/Navbar.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/Reveal.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/StatsCounter.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/ToastContainer.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/VideoModal.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/WhatsAppCta.tsx` | frontend/src | READ |
| `frontend/src/components/common/WhatsAppFloatingBtn.tsx` | frontend/src | TESTED |
| `frontend/src/components/common/statusBadges.test.ts` | frontend/src | TESTED |
| `frontend/src/components/common/usePrefersReducedMotion.ts` | frontend/src | TESTED |
| `frontend/src/components/presentation/DeviceFrame.tsx` | frontend/src | TESTED |
| `frontend/src/components/presentation/PresentationBar.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AboutSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AgenciesDirectoryPage.tsx` | frontend/src | READ |
| `frontend/src/components/public/AgenciesSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AgencyAccessPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AgencyProfilePage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AuthPage.test.ts` | frontend/src | TESTED |
| `frontend/src/components/public/AuthPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/AvailableForSaleSection.tsx` | frontend/src | VERIFIED |
| `frontend/src/components/public/CompareModal.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/CompareTray.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/ContactSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/CrawlableDetailLinks.test.ts` | frontend/src | TESTED |
| `frontend/src/components/public/FeaturedProjects.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/FeaturedProperties.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/HeroSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/MapsPage.test.ts` | frontend/src | TESTED |
| `frontend/src/components/public/MapsPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/MediaGallerySection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/NotFoundPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/ProjectDetailPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/ProjectsListingPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/PropertiesListingPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/PropertyDetailPage.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/SaveSearchModal.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/SavedSearchPanel.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/Scheme45Guide.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/ServicesSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/TestimonialsSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/VideoWalkthroughSection.tsx` | frontend/src | TESTED |
| `frontend/src/components/public/heroSlider.test.ts` | frontend/src | TESTED |
| `frontend/src/components/public/mediaVideoFlow.test.ts` | frontend/src | TESTED |
| `frontend/src/config/appMode.test.ts` | frontend/src | TESTED |
| `frontend/src/config/appMode.ts` | frontend/src | TESTED |
| `frontend/src/config/seo.ts` | frontend/src | TESTED |
| `frontend/src/context/AppContext.test.ts` | frontend/src | TESTED |
| `frontend/src/context/AppContext.tsx` | frontend/src | READ |
| `frontend/src/data/agencyVideosSeed.ts` | frontend/src | TESTED |
| `frontend/src/data/erpSeed.empty.ts` | frontend/src | TESTED |
| `frontend/src/data/erpSeed.ts` | frontend/src | TESTED |
| `frontend/src/data/publicSeed.ts` | frontend/src | TESTED |
| `frontend/src/domain/auditLog.test.ts` | frontend/src | TESTED |
| `frontend/src/domain/auditLog.ts` | frontend/src | TESTED |
| `frontend/src/domain/authSession.ts` | frontend/src | TESTED |
| `frontend/src/domain/mediaPolicy.test.ts` | frontend/src | TESTED |
| `frontend/src/domain/mediaPolicy.ts` | frontend/src | TESTED |
| `frontend/src/domain/mfa.test.ts` | frontend/src | TESTED |
| `frontend/src/domain/mfa.ts` | frontend/src | TESTED |
| `frontend/src/domain/permissions.test.ts` | frontend/src | TESTED |
| `frontend/src/domain/permissions.ts` | frontend/src | TESTED |
| `frontend/src/domain/transactions.test.ts` | frontend/src | TESTED |
| `frontend/src/domain/transactions.ts` | frontend/src | TESTED |
| `frontend/src/index.css` | frontend/src | TESTED |
| `frontend/src/main.tsx` | frontend/src | TESTED |
| `frontend/src/pages/AuthFlowPages.tsx` | frontend/src | TESTED |
| `frontend/src/services/api.ts` | frontend/src | READ |
| `frontend/src/storage/indexedDb.ts` | frontend/src | TESTED |
| `frontend/src/types/index.ts` | frontend/src | READ |
| `frontend/src/utils/apiProperty.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/apiProperty.ts` | frontend/src | TESTED |
| `frontend/src/utils/documentUpload.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/documentUpload.ts` | frontend/src | TESTED |
| `frontend/src/utils/exportCsv.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/exportCsv.ts` | frontend/src | TESTED |
| `frontend/src/utils/logic.sales.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/logic.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/logic.ts` | frontend/src | TESTED |
| `frontend/src/utils/marketplaceFeatures.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/propertyCompare.ts` | frontend/src | TESTED |
| `frontend/src/utils/reloadGuard.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/reloadGuard.ts` | frontend/src | TESTED |
| `frontend/src/utils/savedSearchFilters.ts` | frontend/src | TESTED |
| `frontend/src/utils/whatsapp.test.ts` | frontend/src | TESTED |
| `frontend/src/utils/whatsapp.ts` | frontend/src | READ |
| `frontend/tsc-appfix.txt` | frontend/tsc-appfix.txt | NOT REVIEWED |
| `frontend/tsconfig.json` | frontend/tsconfig.json | READ |
| `frontend/vercel.json` | frontend/vercel.json | READ |
| `frontend/vite.config.ts` | frontend/vite.config.ts | NOT REVIEWED |
| `metadata.json` | (root) | NOT REVIEWED |
| `nginx/nginx.conf` | nginx | READ |
| `package.json` | (root) | READ |
| `scripts/backup.ps1` | scripts | NOT REVIEWED |
| `scripts/backup.sh` | scripts | NOT REVIEWED |
| `scripts/restore-drill.ps1` | scripts | NOT REVIEWED |
| `scripts/wait-for-compose-ready.sh` | scripts | NOT REVIEWED |
| `vercel.json` | (root) | READ |
