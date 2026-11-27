# Current Git File Inventory — 2026-11-27

**Ref:** `main`  
**Commit:** `4baf396fb3074bac134dac9c8bdc40ba2da67bf0`  
**Tree entries:** 642  
**Tracked blobs:** 566  
**Tree truncated:** no

> Inventory is not a code review. All entries below are INVENTORIED only. No file is marked READ or TESTED without separate review evidence.

## Historical ledger reconciliation
- Historical source: `docs/COMPLETE_FILE_AUDIT_LEDGER.md` (originating from `6c92d52`).
- Unique literal path mentions extracted: 203.
- Exact historical paths still present (file or directory): 172.
- Exact historical paths absent: 31.
- Existing current blobs named by old ledger: 172.
- Current blobs not named by old ledger: 394.
- The historical counts 706 and 564 are stale. The current Git tree is authoritative for this snapshot.
- Missing exact paths are not automatically classified as deletions; renamed/migrated/obsolete paths require semantic review.

### Historical paths absent at exact path
- `frontend/src/components/public/HeroSlider.tsx`
- `frontend/src/components/public/CrawlableDetailLinks.tsx`
- `frontend/src/components/common/Sidebar.tsx`
- `frontend/src/components/common/Breadcrumb.tsx`
- `frontend/src/components/common/StatusBadges.tsx`
- `frontend/src/components/admin/adminFileUpload.tsx`
- `frontend/config/appMode.ts`
- `frontend/config/seo.ts`
- `frontend/services/api.ts`
- `frontend/src/pages/_app.tsx`
- `frontend/src/pages/_document.tsx`
- `frontend/src/pages/index.tsx`
- `frontend/src/pages/properties.tsx`
- `frontend/src/pages/agencies.tsx`
- `frontend/src/pages/property-detail.tsx`
- `frontend/src/pages/admin.tsx`
- `frontend/src/pages/login.tsx`
- `frontend/src/pages/register.tsx`
- `frontend/src/pages/reset-password.tsx`
- `frontend/src/pages/verify-email.tsx`
- `backend/tests/Unit/`
- `backend/tests/Feature/`
- `backend/database/migrations/2026_11_02_145317_create_transactions_table.php`
- `backend/database/migrations/2026_11_02_145318_create_projects_table.php`
- `backend/database/migrations/2026_11_03_110000_add_soft_deletes_to_agency_videos.php`
- `backend/database/seeders/`
- `backend/.env.testing`
- `backend/config/`
- `frontend/config/`
- `backend/scripts/`
- `frontend/scripts/`

### Current blobs not named in historical ledger
- `.gitignore`
- `.nvmrc`
- `.screenshots/api-properties.json`
- `CHANGELOG.md`
- `Dockerfile.prod`
- `README.md`
- `backend/.dockerignore`
- `backend/.editorconfig`
- `backend/.env.example`
- `backend/.gitattributes`
- `backend/.gitignore`
- `backend/Dockerfile.prod`
- `backend/README.md`
- `backend/app/Console/Commands/BusinessRecordsAuditCommand.php`
- `backend/app/Console/Commands/EncryptVault.php`
- `backend/app/Console/Commands/LegacyBackfillCommand.php`
- `backend/app/Console/Commands/LegacyOwnershipReportCommand.php`
- `backend/app/Console/Commands/PlatformInventoryCommand.php`
- `backend/app/Console/Commands/ProductionCheck.php`
- `backend/app/Console/Commands/PurgeQuarantine.php`
- `backend/app/Console/Commands/SendSavedSearchAlerts.php`
- `backend/app/Http/Controllers/Api/AgencyVideoController.php`
- `backend/app/Http/Controllers/Api/FinanceController.php`
- `backend/app/Http/Controllers/Api/HealthController.php`
- `backend/app/Http/Controllers/Api/MapController.php`
- `backend/app/Mail/SavedSearchMatchesMail.php`
- `backend/app/Models/AgencySubscription.php`
- `backend/app/Models/AgencyVideo.php`
- `backend/app/Models/AuditLog.php`
- `backend/app/Models/DealerFile.php`
- `backend/app/Models/DocumentAccessLog.php`
- `backend/app/Models/DocumentFolder.php`
- `backend/app/Models/DocumentQuarantine.php`
- `backend/app/Models/DocumentShare.php`
- `backend/app/Models/DocumentVersion.php`
- `backend/app/Models/PaymentSchedule.php`
- `backend/app/Models/Sector.php`
- `backend/app/Models/SubscriptionInvoice.php`
- `backend/app/Models/TransactionPayment.php`
- `backend/app/Models/VideoComment.php`
- `backend/app/Models/VideoLike.php`
- `backend/app/Notifications/ListingUpdated.php`
- `backend/app/Notifications/PasswordChangedNotification.php`
- `backend/app/Notifications/ResetPasswordLink.php`
- `backend/app/Notifications/SuspiciousLoginDetected.php`
- `backend/app/Notifications/VerifyEmailLink.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Support/InquiryCaptcha.php`
- `backend/app/Support/MalwareScanResult.php`
- `backend/app/Support/PropertySearchFilters.php`
- `backend/artisan`
- `backend/bootstrap/app.php`
- `backend/bootstrap/cache/.gitignore`
- `backend/bootstrap/providers.php`
- `backend/composer.lock`
- `backend/config/app.php`
- `backend/config/auth.php`
- `backend/config/cache.php`
- `backend/config/cors.php`
- `backend/config/database.php`
- `backend/config/filesystems.php`
- `backend/config/hashing.php`
- `backend/config/logging.php`
- `backend/config/mail.php`
- `backend/config/malware.php`
- `backend/config/queue.php`
- `backend/config/sanctum.php`
- `backend/config/services.php`
- `backend/config/session.php`
- `backend/config/vault.php`
- `backend/database/.gitignore`
- `backend/database/factories/UserFactory.php`
- `backend/database/init.sql`
- `backend/database/migrations/0001_01_01_000000_create_users_table.php`
- `backend/database/migrations/0001_01_01_000001_create_cache_table.php`
- `backend/database/migrations/0001_01_01_000002_create_jobs_table.php`
- `backend/database/migrations/2026_11_02_145318_create_transactions_table.php`
- `backend/database/migrations/2026_11_02_145319_create_projects_table.php`
- `backend/database/migrations/2026_11_03_100000_create_dealer_files.php`
- `backend/database/migrations/2026_11_03_110000_add_role_to_users.php`
- `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php`
- `backend/database/migrations/2026_11_13_000003_create_support_expenses_invoices_shares_tables.php`
- `backend/database/migrations/2026_11_13_000004_add_mfa_and_login_history.php`
- `backend/database/migrations/2026_11_15_142734_widen_dealer_files_decimal_fields.php`
- `backend/database/migrations/2026_11_15_184000_make_property_id_nullable_in_transactions.php`
- `backend/database/migrations/2026_11_16_000001_create_saved_searches_table.php`
- `backend/database/migrations/2026_11_17_000001_add_vault_scan_lifecycle.php`
- `backend/database/migrations/2026_11_20_000001_add_billing_lifecycle_columns.php`
- `backend/database/migrations/2026_11_20_000002_create_featured_agency_promotions.php`
- `backend/database/migrations/2026_11_20_092340_add_google_oauth_to_users_table.php`
- `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php`
- `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php`
- `backend/database/migrations/2026_11_23_000001_create_maps_table.php`
- `backend/database/migrations/2026_11_23_000002_add_deleted_at_to_transaction_payments.php`
- `backend/database/seeders/AgencyFilesSyncSeeder.php`
- `backend/database/seeders/DatabaseSeeder.php`
- `backend/database/seeders/DemoStaffSeeder.php`
- `backend/database/seeders/EnterpriseDemoSeeder.php`
- `backend/database/seeders/EnterpriseSeeder.php`
- `backend/database/seeders/MapSeeder.php`
- `backend/docker/entrypoint.sh`
- `backend/docker/php-prod.ini`
- `backend/phpunit.xml`
- `backend/public/.htaccess`
- `backend/public/favicon.ico`
- `backend/public/index.php`
- `backend/public/robots.txt`
- `backend/resources/css/app.css`
- `backend/resources/js/app.js`
- `backend/resources/js/bootstrap.js`
- `backend/resources/views/emails/saved-search-matches-text.blade.php`
- `backend/resources/views/emails/saved-search-matches.blade.php`
- `backend/resources/views/welcome.blade.php`
- `backend/route-method-audit.php`
- `backend/routes/console.php`
- `backend/scripts/assert-db-driver.php`
- `backend/scripts/healthcheck.php`
- `backend/scripts/wait-for-db.php`
- `backend/storage/app/.gitignore`
- `backend/storage/app/private/.gitignore`
- `backend/storage/app/private/documents/.gitignore`
- `backend/storage/app/public/.gitignore`
- `backend/storage/framework/.gitignore`
- `backend/storage/framework/cache/.gitignore`
- `backend/storage/framework/cache/data/.gitignore`
- `backend/storage/framework/sessions/.gitignore`
- `backend/storage/framework/testing/.gitignore`
- `backend/storage/framework/views/.gitignore`
- `backend/storage/logs/.gitignore`
- `backend/tests/Feature/AdminStaffSubscriptionTest.php`
- `backend/tests/Feature/AgencyContextTest.php`
- `backend/tests/Feature/AgencyStorageOversightTest.php`
- `backend/tests/Feature/AgencyVideoCommentIsolationTest.php`
- `backend/tests/Feature/AgencyVideoPublishingTest.php`
- `backend/tests/Feature/ApiVersioningTest.php`
- `backend/tests/Feature/AuthFlowsTest.php`
- `backend/tests/Feature/AuthSecurityTest.php`
- `backend/tests/Feature/BackfillIdempotencyTest.php`
- `backend/tests/Feature/BookingConcurrencyTest.php`
- `backend/tests/Feature/CatalogShapeTest.php`
- `backend/tests/Feature/CorrelationIdTest.php`
- `backend/tests/Feature/CrmLeadsTest.php`
- `backend/tests/Feature/CrossAgencyCapabilitiesTest.php`
- `backend/tests/Feature/DatabaseSeederProductionSafetyTest.php`
- `backend/tests/Feature/DealerAgencyLinkTest.php`
- `backend/tests/Feature/DealerFilesAuthorizationTest.php`
- `backend/tests/Feature/DocumentSharesTest.php`
- `backend/tests/Feature/EncryptionAtRestTest.php`
- `backend/tests/Feature/EnterpriseTestCase.php`
- `backend/tests/Feature/ExampleTest.php`
- `backend/tests/Feature/ExpensesTest.php`
- `backend/tests/Feature/FavoritesNotificationsTest.php`
- `backend/tests/Feature/FeaturedAgencyPromotionTest.php`
- `backend/tests/Feature/FileVaultSecurityTest.php`
- `backend/tests/Feature/FinanceIsolationTest.php`
- `backend/tests/Feature/FinanceLifecycleTest.php`
- `backend/tests/Feature/FinanceReportsTest.php`
- `backend/tests/Feature/GoogleOAuthTest.php`
- `backend/tests/Feature/InquiryCaptchaTest.php`
- `backend/tests/Feature/LegacyPlatformOwnershipTest.php`
- `backend/tests/Feature/LegacyTenancyResolverTest.php`
- `backend/tests/Feature/LegacyTransactionIsolationTest.php`
- `backend/tests/Feature/LoginHistoryTest.php`
- `backend/tests/Feature/MalwareScanningTest.php`
- `backend/tests/Feature/MalwareUploadEnforcementTest.php`
- `backend/tests/Feature/MapManagementSafetyTest.php`
- `backend/tests/Feature/MapManagementTest.php`
- `backend/tests/Feature/MfaEnforcementTest.php`
- `backend/tests/Feature/MfaTest.php`
- `backend/tests/Feature/MigrationSmokeTest.php`
- `backend/tests/Feature/MysqlConcurrencyTest.php`
- `backend/tests/Feature/PaymentConfigurationTest.php`
- `backend/tests/Feature/PaymentGatewayTest.php`
- `backend/tests/Feature/PlanLimitEnforcementTest.php`
- `backend/tests/Feature/ProductionReadinessTest.php`
- `backend/tests/Feature/PropertyLifecycleTest.php`
- `backend/tests/Feature/PublicCatalogPrivacyTest.php`
- `backend/tests/Feature/PublicIndexBoundsTest.php`
- `backend/tests/Feature/PublicSubscriptionTest.php`
- `backend/tests/Feature/RateLimitTest.php`
- `backend/tests/Feature/SavedSearchAlertsTest.php`
- `backend/tests/Feature/SecurityHeadersTest.php`
- `backend/tests/Feature/SensitiveMetadataTest.php`
- `backend/tests/Feature/StorageSecurityTest.php`
- `backend/tests/Feature/SubscriptionInvoicesTest.php`
- `backend/tests/Feature/SupportTicketsTest.php`
- `backend/tests/Feature/WorkspaceApprovalTest.php`
- `backend/tests/Feature/WorkspaceHardeningTest.php`
- `backend/tests/Feature/WorkspaceReplaceGuardTest.php`
- `backend/tests/Feature/WorkspaceRoleGateTest.php`
- `backend/tests/Feature/WorkspaceTenantMatrixTest.php`
- `backend/tests/Support/concurrency_request.php`
- `backend/tests/Support/fake_clamd.php`
- `backend/tests/TestCase.php`
- `backend/tests/Unit/ExampleTest.php`
- `backend/tests/Unit/PermissionsCatalogTest.php`
- `backend/tests/Unit/RolePermissionsTest.php`
- `backend/vite.config.js`
- `docker/backend/nginx.conf`
- `docker/backend/supervisord.conf`
- `docker/frontend/nginx.conf`
- `docker/nginx.ci.conf`
- `docs/API.md`
- `docs/AUDIT_LEDGER_HEADER.md`
- `docs/BACKUP_RESTORE.md`
- `docs/COMPLETE_FILE_AUDIT_LEDGER.md`
- `docs/DATABASE.md`
- `docs/DEMO_GUIDE.md`
- `docs/DEPLOYMENT.md`
- `docs/FINAL_COMPLETION_REPORT.md`
- `docs/FINAL_ENTERPRISE_AUDIT.md`
- `docs/INDEPENDENT_REVIEW_20261127.md`
- `docs/PERFORMANCE_AUDIT.md`
- `docs/PRODUCTION_READINESS.md`
- `docs/RELEASE_CHECKLIST.md`
- `docs/SECURITY.md`
- `docs/SECURITY_AUDIT.md`
- `docs/STATUS.md`
- `docs/TEST_EVIDENCE.md`
- `docs/openapi.yaml`
- `documents/01_SYSTEM_ARCHITECTURE.md`
- `documents/02_PRD_USER_STORIES.md`
- `documents/03_DATABASE_SCHEMA.md`
- `documents/04_MODEL_ARCHITECTURE.md`
- `documents/05_SEEDERS_FACTORIES.md`
- `documents/06_SERVICE_LAYER.md`
- `documents/07_VALIDATION_SANITIZATION.md`
- `documents/08_AUTH_RBAC.md`
- `documents/09_CRUD_CONTROLLERS_API.md`
- `documents/10_RESPONSIVE_UI.md`
- `documents/11_LIVE_INTERACTIVE_STATES.md`
- `documents/12_TOAST_NOTIFICATIONS.md`
- `documents/13_SECURITY_HARDENING.md`
- `documents/14_ERROR_HANDLING_LOGGING.md`
- `documents/15_DEPLOYMENT_CICD.md`
- `documents/16_ENTERPRISE_ARCHITECTURE.md`
- `frontend/.dockerignore`
- `frontend/.env.example`
- `frontend/.gitignore`
- `frontend/Dockerfile`
- `frontend/__seo.mjs`
- `frontend/e2e/cross-browser-smoke.spec.ts`
- `frontend/e2e/whatsapp.spec.ts`
- `frontend/index.html`
- `frontend/nginx.conf`
- `frontend/package-lock.json`
- `frontend/playwright.config.ts`
- `frontend/playwright.cross-browser.config.ts`
- `frontend/public/favicon.svg`
- `frontend/public/hero-bg.jpg`
- `frontend/public/hero-slide-2.jpg`
- `frontend/public/hero-slide-3.jpg`
- `frontend/public/hero-slide-4.jpg`
- `frontend/public/hero-slide-5.jpg`
- `frontend/public/image.png`
- `frontend/public/logo.jpg`
- `frontend/public/manifest.webmanifest`
- `frontend/public/og-image.jpg`
- `frontend/public/robots.txt`
- `frontend/public/sample-video.mp4`
- `frontend/public/sw.js`
- `frontend/scripts/browser-admin-interactions.mts`
- `frontend/scripts/browser-audit.mts`
- `frontend/scripts/browser-interactions.mts`
- `frontend/scripts/check-bundle.mjs`
- `frontend/scripts/concurrency-booking.mts`
- `frontend/scripts/legacy-visibility-probe.mts`
- `frontend/scripts/load-test.mts`
- `frontend/scripts/mail-flow-e2e.mts`
- `frontend/scripts/mail-sink.mts`
- `frontend/scripts/mfa-e2e.mts`
- `frontend/scripts/perf-snapshot.mts`
- `frontend/scripts/prerender.mts`
- `frontend/scripts/qa-audit.mts`
- `frontend/scripts/qa-contact.mts`
- `frontend/scripts/qa-theme.mts`
- `frontend/scripts/role-matrix-e2e.mts`
- `frontend/scripts/run-tests.mjs`
- `frontend/scripts/security-probe.mts`
- `frontend/scripts/sensitive-strings.json`
- `frontend/scripts/shot-current.mts`
- `frontend/scripts/shot-mobile-parts.mts`
- `frontend/scripts/verify-fixes.mts`
- `frontend/scripts/workflows-e2e.mts`
- `frontend/src/App.tsx`
- `frontend/src/components/admin/AdminAgencyApprovals.test.ts`
- `frontend/src/components/admin/AdminAgencyDashboard.tsx`
- `frontend/src/components/admin/AdminAgencyOversight.tsx`
- `frontend/src/components/admin/AdminAudit.tsx`
- `frontend/src/components/admin/AdminBuyers.tsx`
- `frontend/src/components/admin/AdminCMS.tsx`
- `frontend/src/components/admin/AdminCrmPipeline.tsx`
- `frontend/src/components/admin/AdminDealers.tsx`
- `frontend/src/components/admin/AdminDocumentVault.tsx`
- `frontend/src/components/admin/AdminEnterpriseUi.tsx`
- `frontend/src/components/admin/AdminFeaturedPromotions.tsx`
- `frontend/src/components/admin/AdminFileManagement.tsx`
- `frontend/src/components/admin/AdminInquiries.tsx`
- `frontend/src/components/admin/AdminLocations.tsx`
- `frontend/src/components/admin/AdminMapManagement.test.ts`
- `frontend/src/components/admin/AdminMediaLibrary.tsx`
- `frontend/src/components/admin/AdminMediaStudio.tsx`
- `frontend/src/components/admin/AdminMfaSettings.tsx`
- `frontend/src/components/admin/AdminModerationDesk.tsx`
- `frontend/src/components/admin/AdminModuleSkeleton.tsx`
- `frontend/src/components/admin/AdminNotifications.tsx`
- `frontend/src/components/admin/AdminOverview.tsx`
- `frontend/src/components/admin/AdminPlotLocator.tsx`
- `frontend/src/components/admin/AdminProjects.tsx`
- `frontend/src/components/admin/AdminProperties.tsx`
- `frontend/src/components/admin/AdminSecurityRoles.tsx`
- `frontend/src/components/admin/AdminSellers.tsx`
- `frontend/src/components/admin/AdminStaffManager.tsx`
- `frontend/src/components/admin/AdminSubscription.tsx`
- `frontend/src/components/admin/AdminSupportDesk.tsx`
- `frontend/src/components/admin/AdminTransactions.tsx`
- `frontend/src/components/admin/adminFileUpload.test.ts`
- `frontend/src/components/common/AgencyDetailsModal.test.ts`
- `frontend/src/components/common/ConfirmDialog.tsx`
- `frontend/src/components/common/EmptyState.tsx`
- `frontend/src/components/common/FavoriteHeart.tsx`
- `frontend/src/components/common/Footer.tsx`
- `frontend/src/components/common/ImageWithFallback.tsx`
- `frontend/src/components/common/Lightbox.tsx`
- `frontend/src/components/common/MfaChallengeForm.tsx`
- `frontend/src/components/common/StatsCounter.tsx`
- `frontend/src/components/common/ToastContainer.tsx`
- `frontend/src/components/common/VideoModal.tsx`
- `frontend/src/components/common/WhatsAppCta.tsx`
- `frontend/src/components/common/WhatsAppFloatingBtn.tsx`
- `frontend/src/components/common/statusBadges.test.ts`
- `frontend/src/components/common/usePrefersReducedMotion.ts`
- `frontend/src/components/presentation/DeviceFrame.tsx`
- `frontend/src/components/presentation/PresentationBar.tsx`
- `frontend/src/components/public/AboutSection.tsx`
- `frontend/src/components/public/AgenciesSection.tsx`
- `frontend/src/components/public/AgencyAccessPage.tsx`
- `frontend/src/components/public/AuthPage.test.ts`
- `frontend/src/components/public/AvailableForSaleSection.tsx`
- `frontend/src/components/public/CompareModal.tsx`
- `frontend/src/components/public/CompareTray.tsx`
- `frontend/src/components/public/ContactSection.tsx`
- `frontend/src/components/public/CrawlableDetailLinks.test.ts`
- `frontend/src/components/public/FeaturedProjects.tsx`
- `frontend/src/components/public/FeaturedProperties.tsx`
- `frontend/src/components/public/MapsPage.test.ts`
- `frontend/src/components/public/MediaGallerySection.tsx`
- `frontend/src/components/public/ProjectDetailPage.tsx`
- `frontend/src/components/public/ProjectsListingPage.tsx`
- `frontend/src/components/public/SaveSearchModal.tsx`
- `frontend/src/components/public/SavedSearchPanel.tsx`
- `frontend/src/components/public/Scheme45Guide.tsx`
- `frontend/src/components/public/ServicesSection.tsx`
- `frontend/src/components/public/TestimonialsSection.tsx`
- `frontend/src/components/public/VideoWalkthroughSection.tsx`
- `frontend/src/components/public/heroSlider.test.ts`
- `frontend/src/components/public/mediaVideoFlow.test.ts`
- `frontend/src/config/appMode.test.ts`
- `frontend/src/config/appMode.ts`
- `frontend/src/config/seo.ts`
- `frontend/src/config/vercel-routes.test.ts`
- `frontend/src/context/AppContext.test.ts`
- `frontend/src/data/agencyVideosSeed.ts`
- `frontend/src/data/erpSeed.empty.ts`
- `frontend/src/data/erpSeed.ts`
- `frontend/src/data/publicSeed.ts`
- `frontend/src/domain/auditLog.test.ts`
- `frontend/src/domain/auditLog.ts`
- `frontend/src/domain/mediaPolicy.test.ts`
- `frontend/src/domain/mfa.test.ts`
- `frontend/src/domain/permissions.test.ts`
- `frontend/src/domain/transactions.test.ts`
- `frontend/src/index.css`
- `frontend/src/main.tsx`
- `frontend/src/pages/AuthFlowPages.tsx`
- `frontend/src/services/api.ts`
- `frontend/src/storage/indexedDb.ts`
- `frontend/src/utils/apiProperty.test.ts`
- `frontend/src/utils/documentUpload.test.ts`
- `frontend/src/utils/exportCsv.test.ts`
- `frontend/src/utils/marketplaceFeatures.test.ts`
- `frontend/src/utils/whatsapp.test.ts`
- `frontend/src/utils/whatsapp.ts`
- `frontend/tsconfig.json`
- `frontend/vercel.json`
- `frontend/vite.config.ts`
- `metadata.json`
- `nginx/nginx.conf`
- `package.json`
- `scripts/backup.ps1`
- `scripts/backup.sh`
- `scripts/restore-drill.ps1`
- `scripts/wait-for-compose-ready.sh`
- `vercel.json`

## Complete current tracked blob inventory
| # | Path | Kind | Audit state |
|---:|---|---|---|
| 1 | `.github/workflows/ci.yml` | Source/config/build | INVENTORIED — READ pending |
| 2 | `.github/workflows/docker-ci.yml` | Source/config/build | INVENTORIED — READ pending |
| 3 | `.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 4 | `.nvmrc` | Source/config/build | INVENTORIED — READ pending |
| 5 | `.screenshots/api-properties.json` | Source/config/build | INVENTORIED — READ pending |
| 6 | `CHANGELOG.md` | Documentation | INVENTORIED — READ pending |
| 7 | `Dockerfile.prod` | Source/config/build | INVENTORIED — READ pending |
| 8 | `README.md` | Documentation | INVENTORIED — READ pending |
| 9 | `backend/.dockerignore` | Source/config/build | INVENTORIED — READ pending |
| 10 | `backend/.editorconfig` | Source/config/build | INVENTORIED — READ pending |
| 11 | `backend/.env.example` | Source/config/build | INVENTORIED — READ pending |
| 12 | `backend/.gitattributes` | Source/config/build | INVENTORIED — READ pending |
| 13 | `backend/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 14 | `backend/Dockerfile` | Source/config/build | INVENTORIED — READ pending |
| 15 | `backend/Dockerfile.prod` | Source/config/build | INVENTORIED — READ pending |
| 16 | `backend/README.md` | Documentation | INVENTORIED — READ pending |
| 17 | `backend/app/Console/Commands/BusinessRecordsAuditCommand.php` | Source/config/build | INVENTORIED — READ pending |
| 18 | `backend/app/Console/Commands/EncryptVault.php` | Source/config/build | INVENTORIED — READ pending |
| 19 | `backend/app/Console/Commands/LegacyBackfillCommand.php` | Source/config/build | INVENTORIED — READ pending |
| 20 | `backend/app/Console/Commands/LegacyOwnershipReportCommand.php` | Source/config/build | INVENTORIED — READ pending |
| 21 | `backend/app/Console/Commands/PlatformInventoryCommand.php` | Source/config/build | INVENTORIED — READ pending |
| 22 | `backend/app/Console/Commands/ProductionCheck.php` | Source/config/build | INVENTORIED — READ pending |
| 23 | `backend/app/Console/Commands/PurgeQuarantine.php` | Source/config/build | INVENTORIED — READ pending |
| 24 | `backend/app/Console/Commands/SendSavedSearchAlerts.php` | Source/config/build | INVENTORIED — READ pending |
| 25 | `backend/app/Http/Controllers/Api/AdminAgencyController.php` | Source/config/build | INVENTORIED — READ pending |
| 26 | `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php` | Source/config/build | INVENTORIED — READ pending |
| 27 | `backend/app/Http/Controllers/Api/AdminDashboardController.php` | Source/config/build | INVENTORIED — READ pending |
| 28 | `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` | Source/config/build | INVENTORIED — READ pending |
| 29 | `backend/app/Http/Controllers/Api/AdminSystemController.php` | Source/config/build | INVENTORIED — READ pending |
| 30 | `backend/app/Http/Controllers/Api/AdminUserController.php` | Source/config/build | INVENTORIED — READ pending |
| 31 | `backend/app/Http/Controllers/Api/AgencyController.php` | Source/config/build | INVENTORIED — READ pending |
| 32 | `backend/app/Http/Controllers/Api/AgencyStaffController.php` | Source/config/build | INVENTORIED — READ pending |
| 33 | `backend/app/Http/Controllers/Api/AgencyVideoController.php` | Source/config/build | INVENTORIED — READ pending |
| 34 | `backend/app/Http/Controllers/Api/AuthController.php` | Source/config/build | INVENTORIED — READ pending |
| 35 | `backend/app/Http/Controllers/Api/AuthorizesApi.php` | Source/config/build | INVENTORIED — READ pending |
| 36 | `backend/app/Http/Controllers/Api/DealerController.php` | Source/config/build | INVENTORIED — READ pending |
| 37 | `backend/app/Http/Controllers/Api/DealerFilesController.php` | Source/config/build | INVENTORIED — READ pending |
| 38 | `backend/app/Http/Controllers/Api/DocumentController.php` | Source/config/build | INVENTORIED — READ pending |
| 39 | `backend/app/Http/Controllers/Api/FavoriteController.php` | Source/config/build | INVENTORIED — READ pending |
| 40 | `backend/app/Http/Controllers/Api/FeaturedAgencyController.php` | Source/config/build | INVENTORIED — READ pending |
| 41 | `backend/app/Http/Controllers/Api/FinanceController.php` | Source/config/build | INVENTORIED — READ pending |
| 42 | `backend/app/Http/Controllers/Api/HealthController.php` | Source/config/build | INVENTORIED — READ pending |
| 43 | `backend/app/Http/Controllers/Api/InquiryController.php` | Source/config/build | INVENTORIED — READ pending |
| 44 | `backend/app/Http/Controllers/Api/LeadController.php` | Source/config/build | INVENTORIED — READ pending |
| 45 | `backend/app/Http/Controllers/Api/LocationController.php` | Source/config/build | INVENTORIED — READ pending |
| 46 | `backend/app/Http/Controllers/Api/MapController.php` | Source/config/build | INVENTORIED — READ pending |
| 47 | `backend/app/Http/Controllers/Api/MediaController.php` | Source/config/build | INVENTORIED — READ pending |
| 48 | `backend/app/Http/Controllers/Api/NotificationController.php` | Source/config/build | INVENTORIED — READ pending |
| 49 | `backend/app/Http/Controllers/Api/PaymentController.php` | Source/config/build | INVENTORIED — READ pending |
| 50 | `backend/app/Http/Controllers/Api/ProjectController.php` | Source/config/build | INVENTORIED — READ pending |
| 51 | `backend/app/Http/Controllers/Api/PropertyController.php` | Source/config/build | INVENTORIED — READ pending |
| 52 | `backend/app/Http/Controllers/Api/SavedSearchController.php` | Source/config/build | INVENTORIED — READ pending |
| 53 | `backend/app/Http/Controllers/Api/SubscriptionController.php` | Source/config/build | INVENTORIED — READ pending |
| 54 | `backend/app/Http/Controllers/Api/SupportController.php` | Source/config/build | INVENTORIED — READ pending |
| 55 | `backend/app/Http/Controllers/Api/TransactionController.php` | Source/config/build | INVENTORIED — READ pending |
| 56 | `backend/app/Http/Controllers/Api/WorkspaceController.php` | Source/config/build | INVENTORIED — READ pending |
| 57 | `backend/app/Http/Controllers/Controller.php` | Source/config/build | INVENTORIED — READ pending |
| 58 | `backend/app/Http/Middleware/AgencyContext.php` | Source/config/build | INVENTORIED — READ pending |
| 59 | `backend/app/Http/Middleware/CorrelationId.php` | Source/config/build | INVENTORIED — READ pending |
| 60 | `backend/app/Http/Middleware/EnsureMfaVerified.php` | Source/config/build | INVENTORIED — READ pending |
| 61 | `backend/app/Http/Middleware/SecurityHeaders.php` | Source/config/build | INVENTORIED — READ pending |
| 62 | `backend/app/Http/Middleware/TrustProxies.php` | Source/config/build | INVENTORIED — READ pending |
| 63 | `backend/app/Mail/SavedSearchMatchesMail.php` | Source/config/build | INVENTORIED — READ pending |
| 64 | `backend/app/Models/Agency.php` | Source/config/build | INVENTORIED — READ pending |
| 65 | `backend/app/Models/AgencyInvitation.php` | Source/config/build | INVENTORIED — READ pending |
| 66 | `backend/app/Models/AgencyMember.php` | Source/config/build | INVENTORIED — READ pending |
| 67 | `backend/app/Models/AgencyPromotion.php` | Source/config/build | INVENTORIED — READ pending |
| 68 | `backend/app/Models/AgencySubscription.php` | Source/config/build | INVENTORIED — READ pending |
| 69 | `backend/app/Models/AgencyVideo.php` | Source/config/build | INVENTORIED — READ pending |
| 70 | `backend/app/Models/AuditLog.php` | Source/config/build | INVENTORIED — READ pending |
| 71 | `backend/app/Models/Block.php` | Source/config/build | INVENTORIED — READ pending |
| 72 | `backend/app/Models/BusinessRecord.php` | Source/config/build | INVENTORIED — READ pending |
| 73 | `backend/app/Models/Commission.php` | Source/config/build | INVENTORIED — READ pending |
| 74 | `backend/app/Models/Dealer.php` | Source/config/build | INVENTORIED — READ pending |
| 75 | `backend/app/Models/DealerFile.php` | Source/config/build | INVENTORIED — READ pending |
| 76 | `backend/app/Models/Document.php` | Source/config/build | INVENTORIED — READ pending |
| 77 | `backend/app/Models/DocumentAccessLog.php` | Source/config/build | INVENTORIED — READ pending |
| 78 | `backend/app/Models/DocumentFolder.php` | Source/config/build | INVENTORIED — READ pending |
| 79 | `backend/app/Models/DocumentQuarantine.php` | Source/config/build | INVENTORIED — READ pending |
| 80 | `backend/app/Models/DocumentShare.php` | Source/config/build | INVENTORIED — READ pending |
| 81 | `backend/app/Models/DocumentVersion.php` | Source/config/build | INVENTORIED — READ pending |
| 82 | `backend/app/Models/EmailVerificationToken.php` | Source/config/build | INVENTORIED — READ pending |
| 83 | `backend/app/Models/Expense.php` | Source/config/build | INVENTORIED — READ pending |
| 84 | `backend/app/Models/Inquiry.php` | Source/config/build | INVENTORIED — READ pending |
| 85 | `backend/app/Models/LeadActivity.php` | Source/config/build | INVENTORIED — READ pending |
| 86 | `backend/app/Models/LoginHistory.php` | Source/config/build | INVENTORIED — READ pending |
| 87 | `backend/app/Models/Map.php` | Source/config/build | INVENTORIED — READ pending |
| 88 | `backend/app/Models/MediaFile.php` | Source/config/build | INVENTORIED — READ pending |
| 89 | `backend/app/Models/PaymentSchedule.php` | Source/config/build | INVENTORIED — READ pending |
| 90 | `backend/app/Models/Permission.php` | Source/config/build | INVENTORIED — READ pending |
| 91 | `backend/app/Models/Project.php` | Source/config/build | INVENTORIED — READ pending |
| 92 | `backend/app/Models/PromotionPackage.php` | Source/config/build | INVENTORIED — READ pending |
| 93 | `backend/app/Models/Property.php` | Source/config/build | INVENTORIED — READ pending |
| 94 | `backend/app/Models/PropertyCategory.php` | Source/config/build | INVENTORIED — READ pending |
| 95 | `backend/app/Models/PropertyFavorite.php` | Source/config/build | INVENTORIED — READ pending |
| 96 | `backend/app/Models/PropertyStatusHistory.php` | Source/config/build | INVENTORIED — READ pending |
| 97 | `backend/app/Models/PropertyView.php` | Source/config/build | INVENTORIED — READ pending |
| 98 | `backend/app/Models/Role.php` | Source/config/build | INVENTORIED — READ pending |
| 99 | `backend/app/Models/SavedSearch.php` | Source/config/build | INVENTORIED — READ pending |
| 100 | `backend/app/Models/Sector.php` | Source/config/build | INVENTORIED — READ pending |
| 101 | `backend/app/Models/SubscriptionInvoice.php` | Source/config/build | INVENTORIED — READ pending |
| 102 | `backend/app/Models/SubscriptionPlan.php` | Source/config/build | INVENTORIED — READ pending |
| 103 | `backend/app/Models/SupportMessage.php` | Source/config/build | INVENTORIED — READ pending |
| 104 | `backend/app/Models/SupportTicket.php` | Source/config/build | INVENTORIED — READ pending |
| 105 | `backend/app/Models/SystemSetting.php` | Source/config/build | INVENTORIED — READ pending |
| 106 | `backend/app/Models/Transaction.php` | Source/config/build | INVENTORIED — READ pending |
| 107 | `backend/app/Models/TransactionPayment.php` | Source/config/build | INVENTORIED — READ pending |
| 108 | `backend/app/Models/User.php` | Source/config/build | INVENTORIED — READ pending |
| 109 | `backend/app/Models/VideoComment.php` | Source/config/build | INVENTORIED — READ pending |
| 110 | `backend/app/Models/VideoLike.php` | Source/config/build | INVENTORIED — READ pending |
| 111 | `backend/app/Notifications/ListingUpdated.php` | Source/config/build | INVENTORIED — READ pending |
| 112 | `backend/app/Notifications/PasswordChangedNotification.php` | Source/config/build | INVENTORIED — READ pending |
| 113 | `backend/app/Notifications/ResetPasswordLink.php` | Source/config/build | INVENTORIED — READ pending |
| 114 | `backend/app/Notifications/SuspiciousLoginDetected.php` | Source/config/build | INVENTORIED — READ pending |
| 115 | `backend/app/Notifications/VerifyEmailLink.php` | Source/config/build | INVENTORIED — READ pending |
| 116 | `backend/app/Providers/AppServiceProvider.php` | Source/config/build | INVENTORIED — READ pending |
| 117 | `backend/app/Services/AgencyOnboarding.php` | Source/config/build | INVENTORIED — READ pending |
| 118 | `backend/app/Services/AgencyPromotionService.php` | Source/config/build | INVENTORIED — READ pending |
| 119 | `backend/app/Services/ClamAVScanner.php` | Source/config/build | INVENTORIED — READ pending |
| 120 | `backend/app/Services/DisabledMalwareScanner.php` | Source/config/build | INVENTORIED — READ pending |
| 121 | `backend/app/Services/FileVaultCrypto.php` | Source/config/build | INVENTORIED — READ pending |
| 122 | `backend/app/Services/MalwareScanner.php` | Source/config/build | INVENTORIED — READ pending |
| 123 | `backend/app/Services/SecureFileStore.php` | Source/config/build | INVENTORIED — READ pending |
| 124 | `backend/app/Support/InquiryCaptcha.php` | Source/config/build | INVENTORIED — READ pending |
| 125 | `backend/app/Support/LegacyBackfillBackup.php` | Source/config/build | INVENTORIED — READ pending |
| 126 | `backend/app/Support/LegacyTenancyResolver.php` | Source/config/build | INVENTORIED — READ pending |
| 127 | `backend/app/Support/MalwareScanResult.php` | Source/config/build | INVENTORIED — READ pending |
| 128 | `backend/app/Support/MfaService.php` | Source/config/build | INVENTORIED — READ pending |
| 129 | `backend/app/Support/MfaVerification.php` | Source/config/build | INVENTORIED — READ pending |
| 130 | `backend/app/Support/Permissions.php` | Source/config/build | INVENTORIED — READ pending |
| 131 | `backend/app/Support/PlanLimits.php` | Source/config/build | INVENTORIED — READ pending |
| 132 | `backend/app/Support/PropertySearchFilters.php` | Source/config/build | INVENTORIED — READ pending |
| 133 | `backend/app/Support/RolePermissions.php` | Source/config/build | INVENTORIED — READ pending |
| 134 | `backend/app/Support/UploadSecurity.php` | Source/config/build | INVENTORIED — READ pending |
| 135 | `backend/artisan` | Source/config/build | INVENTORIED — READ pending |
| 136 | `backend/bootstrap/app.php` | Source/config/build | INVENTORIED — READ pending |
| 137 | `backend/bootstrap/cache/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 138 | `backend/bootstrap/providers.php` | Source/config/build | INVENTORIED — READ pending |
| 139 | `backend/composer.json` | Source/config/build | INVENTORIED — READ pending |
| 140 | `backend/composer.lock` | Source/config/build | INVENTORIED — READ pending |
| 141 | `backend/config/app.php` | Source/config/build | INVENTORIED — READ pending |
| 142 | `backend/config/auth.php` | Source/config/build | INVENTORIED — READ pending |
| 143 | `backend/config/cache.php` | Source/config/build | INVENTORIED — READ pending |
| 144 | `backend/config/cors.php` | Source/config/build | INVENTORIED — READ pending |
| 145 | `backend/config/database.php` | Source/config/build | INVENTORIED — READ pending |
| 146 | `backend/config/filesystems.php` | Source/config/build | INVENTORIED — READ pending |
| 147 | `backend/config/hashing.php` | Source/config/build | INVENTORIED — READ pending |
| 148 | `backend/config/logging.php` | Source/config/build | INVENTORIED — READ pending |
| 149 | `backend/config/mail.php` | Source/config/build | INVENTORIED — READ pending |
| 150 | `backend/config/malware.php` | Source/config/build | INVENTORIED — READ pending |
| 151 | `backend/config/queue.php` | Source/config/build | INVENTORIED — READ pending |
| 152 | `backend/config/sanctum.php` | Source/config/build | INVENTORIED — READ pending |
| 153 | `backend/config/services.php` | Source/config/build | INVENTORIED — READ pending |
| 154 | `backend/config/session.php` | Source/config/build | INVENTORIED — READ pending |
| 155 | `backend/config/vault.php` | Source/config/build | INVENTORIED — READ pending |
| 156 | `backend/database/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 157 | `backend/database/factories/UserFactory.php` | Source/config/build | INVENTORIED — READ pending |
| 158 | `backend/database/init.sql` | Source/config/build | INVENTORIED — READ pending |
| 159 | `backend/database/migrations/0001_01_01_000000_create_users_table.php` | Source/config/build | INVENTORIED — READ pending |
| 160 | `backend/database/migrations/0001_01_01_000001_create_cache_table.php` | Source/config/build | INVENTORIED — READ pending |
| 161 | `backend/database/migrations/0001_01_01_000002_create_jobs_table.php` | Source/config/build | INVENTORIED — READ pending |
| 162 | `backend/database/migrations/2026_11_02_145235_create_personal_access_tokens_table.php` | Source/config/build | INVENTORIED — READ pending |
| 163 | `backend/database/migrations/2026_11_02_145311_create_dealers_table.php` | Source/config/build | INVENTORIED — READ pending |
| 164 | `backend/database/migrations/2026_11_02_145312_create_properties_table.php` | Source/config/build | INVENTORIED — READ pending |
| 165 | `backend/database/migrations/2026_11_02_145313_create_agency_videos_table.php` | Source/config/build | INVENTORIED — READ pending |
| 166 | `backend/database/migrations/2026_11_02_145315_create_video_comments_table.php` | Source/config/build | INVENTORIED — READ pending |
| 167 | `backend/database/migrations/2026_11_02_145316_create_inquiries_table.php` | Source/config/build | INVENTORIED — READ pending |
| 168 | `backend/database/migrations/2026_11_02_145318_create_transactions_table.php` | Source/config/build | INVENTORIED — READ pending |
| 169 | `backend/database/migrations/2026_11_02_145319_create_projects_table.php` | Source/config/build | INVENTORIED — READ pending |
| 170 | `backend/database/migrations/2026_11_03_100000_create_dealer_files.php` | Source/config/build | INVENTORIED — READ pending |
| 171 | `backend/database/migrations/2026_11_03_110000_add_role_to_users.php` | Source/config/build | INVENTORIED — READ pending |
| 172 | `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php` | Source/config/build | INVENTORIED — READ pending |
| 173 | `backend/database/migrations/2026_11_03_130000_add_soft_deletes_to_remaining_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 174 | `backend/database/migrations/2026_11_03_160000_create_audit_logs_table.php` | Source/config/build | INVENTORIED — READ pending |
| 175 | `backend/database/migrations/2026_11_03_170000_create_business_records_table.php` | Source/config/build | INVENTORIED — READ pending |
| 176 | `backend/database/migrations/2026_11_05_100000_add_indexes_to_business_records.php` | Source/config/build | INVENTORIED — READ pending |
| 177 | `backend/database/migrations/2026_11_05_110000_add_extra_fields_to_properties.php` | Source/config/build | INVENTORIED — READ pending |
| 178 | `backend/database/migrations/2026_11_10_000001_create_agencies_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 179 | `backend/database/migrations/2026_11_10_000002_create_rbac_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 180 | `backend/database/migrations/2026_11_10_000003_create_agency_members_table.php` | Source/config/build | INVENTORIED — READ pending |
| 181 | `backend/database/migrations/2026_11_10_000004_add_enterprise_fields_to_users.php` | Source/config/build | INVENTORIED — READ pending |
| 182 | `backend/database/migrations/2026_11_10_000005_create_location_and_category_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 183 | `backend/database/migrations/2026_11_10_000006_add_workflow_fields_to_properties.php` | Source/config/build | INVENTORIED — READ pending |
| 184 | `backend/database/migrations/2026_11_10_000007_create_property_workflow_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 185 | `backend/database/migrations/2026_11_10_000008_create_document_vault_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 186 | `backend/database/migrations/2026_11_10_000009_create_media_files_table.php` | Source/config/build | INVENTORIED — READ pending |
| 187 | `backend/database/migrations/2026_11_10_000010_add_crm_fields_to_inquiries.php` | Source/config/build | INVENTORIED — READ pending |
| 188 | `backend/database/migrations/2026_11_10_000011_create_finance_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 189 | `backend/database/migrations/2026_11_10_000012_create_notifications_table.php` | Source/config/build | INVENTORIED — READ pending |
| 190 | `backend/database/migrations/2026_11_10_000013_create_subscription_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 191 | `backend/database/migrations/2026_11_10_000014_create_system_settings_table.php` | Source/config/build | INVENTORIED — READ pending |
| 192 | `backend/database/migrations/2026_11_10_000015_extend_audit_logs_and_business_records.php` | Source/config/build | INVENTORIED — READ pending |
| 193 | `backend/database/migrations/2026_11_10_000016_backfill_enterprise_relations.php` | Source/config/build | INVENTORIED — READ pending |
| 194 | `backend/database/migrations/2026_11_10_000017_add_full_fields_to_properties.php` | Source/config/build | INVENTORIED — READ pending |
| 195 | `backend/database/migrations/2026_11_10_000018_add_pending_changes_to_properties.php` | Source/config/build | INVENTORIED — READ pending |
| 196 | `backend/database/migrations/2026_11_10_000019_add_video_engagement_attribution.php` | Source/config/build | INVENTORIED — READ pending |
| 197 | `backend/database/migrations/2026_11_10_000020_add_finance_detail_columns_to_transactions.php` | Source/config/build | INVENTORIED — READ pending |
| 198 | `backend/database/migrations/2026_11_13_000001_encrypt_dealer_cnic_at_rest.php` | Source/config/build | INVENTORIED — READ pending |
| 199 | `backend/database/migrations/2026_11_13_000002_create_email_verification_tokens.php` | Source/config/build | INVENTORIED — READ pending |
| 200 | `backend/database/migrations/2026_11_13_000003_create_support_expenses_invoices_shares_tables.php` | Source/config/build | INVENTORIED — READ pending |
| 201 | `backend/database/migrations/2026_11_13_000004_add_mfa_and_login_history.php` | Source/config/build | INVENTORIED — READ pending |
| 202 | `backend/database/migrations/2026_11_15_142734_widen_dealer_files_decimal_fields.php` | Source/config/build | INVENTORIED — READ pending |
| 203 | `backend/database/migrations/2026_11_15_184000_make_property_id_nullable_in_transactions.php` | Source/config/build | INVENTORIED — READ pending |
| 204 | `backend/database/migrations/2026_11_16_000001_create_saved_searches_table.php` | Source/config/build | INVENTORIED — READ pending |
| 205 | `backend/database/migrations/2026_11_17_000001_add_vault_scan_lifecycle.php` | Source/config/build | INVENTORIED — READ pending |
| 206 | `backend/database/migrations/2026_11_20_000001_add_billing_lifecycle_columns.php` | Source/config/build | INVENTORIED — READ pending |
| 207 | `backend/database/migrations/2026_11_20_000002_create_featured_agency_promotions.php` | Source/config/build | INVENTORIED — READ pending |
| 208 | `backend/database/migrations/2026_11_20_092340_add_google_oauth_to_users_table.php` | Source/config/build | INVENTORIED — READ pending |
| 209 | `backend/database/migrations/2026_11_22_000003_add_public_marketplace_indexes.php` | Source/config/build | INVENTORIED — READ pending |
| 210 | `backend/database/migrations/2026_11_22_000004_add_transactions_agency_created_index.php` | Source/config/build | INVENTORIED — READ pending |
| 211 | `backend/database/migrations/2026_11_23_000001_create_maps_table.php` | Source/config/build | INVENTORIED — READ pending |
| 212 | `backend/database/migrations/2026_11_23_000002_add_deleted_at_to_transaction_payments.php` | Source/config/build | INVENTORIED — READ pending |
| 213 | `backend/database/seeders/AgencyFilesSyncSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 214 | `backend/database/seeders/DatabaseSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 215 | `backend/database/seeders/DemoStaffSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 216 | `backend/database/seeders/EnterpriseDemoSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 217 | `backend/database/seeders/EnterpriseSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 218 | `backend/database/seeders/MapSeeder.php` | Source/config/build | INVENTORIED — READ pending |
| 219 | `backend/docker/entrypoint.sh` | Source/config/build | INVENTORIED — READ pending |
| 220 | `backend/docker/php-prod.ini` | Source/config/build | INVENTORIED — READ pending |
| 221 | `backend/package.json` | Source/config/build | INVENTORIED — READ pending |
| 222 | `backend/phpunit.xml` | Source/config/build | INVENTORIED — READ pending |
| 223 | `backend/public/.htaccess` | Source/config/build | INVENTORIED — READ pending |
| 224 | `backend/public/favicon.ico` | Binary/asset | INVENTORIED — READ pending |
| 225 | `backend/public/index.php` | Source/config/build | INVENTORIED — READ pending |
| 226 | `backend/public/robots.txt` | Documentation | INVENTORIED — READ pending |
| 227 | `backend/resources/css/app.css` | Source/config/build | INVENTORIED — READ pending |
| 228 | `backend/resources/js/app.js` | Source/config/build | INVENTORIED — READ pending |
| 229 | `backend/resources/js/bootstrap.js` | Source/config/build | INVENTORIED — READ pending |
| 230 | `backend/resources/views/emails/saved-search-matches-text.blade.php` | Source/config/build | INVENTORIED — READ pending |
| 231 | `backend/resources/views/emails/saved-search-matches.blade.php` | Source/config/build | INVENTORIED — READ pending |
| 232 | `backend/resources/views/welcome.blade.php` | Source/config/build | INVENTORIED — READ pending |
| 233 | `backend/route-method-audit.php` | Source/config/build | INVENTORIED — READ pending |
| 234 | `backend/routes/api.php` | Source/config/build | INVENTORIED — READ pending |
| 235 | `backend/routes/api_definitions.php` | Source/config/build | INVENTORIED — READ pending |
| 236 | `backend/routes/console.php` | Source/config/build | INVENTORIED — READ pending |
| 237 | `backend/routes/web.php` | Source/config/build | INVENTORIED — READ pending |
| 238 | `backend/scripts/assert-db-driver.php` | Source/config/build | INVENTORIED — READ pending |
| 239 | `backend/scripts/healthcheck.php` | Source/config/build | INVENTORIED — READ pending |
| 240 | `backend/scripts/wait-for-db.php` | Source/config/build | INVENTORIED — READ pending |
| 241 | `backend/storage/app/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 242 | `backend/storage/app/private/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 243 | `backend/storage/app/private/documents/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 244 | `backend/storage/app/public/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 245 | `backend/storage/framework/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 246 | `backend/storage/framework/cache/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 247 | `backend/storage/framework/cache/data/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 248 | `backend/storage/framework/sessions/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 249 | `backend/storage/framework/testing/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 250 | `backend/storage/framework/views/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 251 | `backend/storage/logs/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 252 | `backend/tests/Feature/AdminStaffSubscriptionTest.php` | Source/config/build | INVENTORIED — READ pending |
| 253 | `backend/tests/Feature/AgencyContextTest.php` | Source/config/build | INVENTORIED — READ pending |
| 254 | `backend/tests/Feature/AgencyStorageOversightTest.php` | Source/config/build | INVENTORIED — READ pending |
| 255 | `backend/tests/Feature/AgencyVideoCommentIsolationTest.php` | Source/config/build | INVENTORIED — READ pending |
| 256 | `backend/tests/Feature/AgencyVideoPublishingTest.php` | Source/config/build | INVENTORIED — READ pending |
| 257 | `backend/tests/Feature/ApiVersioningTest.php` | Source/config/build | INVENTORIED — READ pending |
| 258 | `backend/tests/Feature/AuthFlowsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 259 | `backend/tests/Feature/AuthSecurityTest.php` | Source/config/build | INVENTORIED — READ pending |
| 260 | `backend/tests/Feature/BackfillIdempotencyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 261 | `backend/tests/Feature/BookingConcurrencyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 262 | `backend/tests/Feature/CatalogShapeTest.php` | Source/config/build | INVENTORIED — READ pending |
| 263 | `backend/tests/Feature/CorrelationIdTest.php` | Source/config/build | INVENTORIED — READ pending |
| 264 | `backend/tests/Feature/CrmLeadsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 265 | `backend/tests/Feature/CrossAgencyCapabilitiesTest.php` | Source/config/build | INVENTORIED — READ pending |
| 266 | `backend/tests/Feature/DatabaseSeederProductionSafetyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 267 | `backend/tests/Feature/DealerAgencyLinkTest.php` | Source/config/build | INVENTORIED — READ pending |
| 268 | `backend/tests/Feature/DealerFilesAuthorizationTest.php` | Source/config/build | INVENTORIED — READ pending |
| 269 | `backend/tests/Feature/DocumentSharesTest.php` | Source/config/build | INVENTORIED — READ pending |
| 270 | `backend/tests/Feature/EncryptionAtRestTest.php` | Source/config/build | INVENTORIED — READ pending |
| 271 | `backend/tests/Feature/EnterpriseTestCase.php` | Source/config/build | INVENTORIED — READ pending |
| 272 | `backend/tests/Feature/ExampleTest.php` | Source/config/build | INVENTORIED — READ pending |
| 273 | `backend/tests/Feature/ExpensesTest.php` | Source/config/build | INVENTORIED — READ pending |
| 274 | `backend/tests/Feature/FavoritesNotificationsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 275 | `backend/tests/Feature/FeaturedAgencyPromotionTest.php` | Source/config/build | INVENTORIED — READ pending |
| 276 | `backend/tests/Feature/FileVaultSecurityTest.php` | Source/config/build | INVENTORIED — READ pending |
| 277 | `backend/tests/Feature/FinanceIsolationTest.php` | Source/config/build | INVENTORIED — READ pending |
| 278 | `backend/tests/Feature/FinanceLifecycleTest.php` | Source/config/build | INVENTORIED — READ pending |
| 279 | `backend/tests/Feature/FinanceReportsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 280 | `backend/tests/Feature/GoogleOAuthTest.php` | Source/config/build | INVENTORIED — READ pending |
| 281 | `backend/tests/Feature/InquiryCaptchaTest.php` | Source/config/build | INVENTORIED — READ pending |
| 282 | `backend/tests/Feature/LegacyPlatformOwnershipTest.php` | Source/config/build | INVENTORIED — READ pending |
| 283 | `backend/tests/Feature/LegacyTenancyResolverTest.php` | Source/config/build | INVENTORIED — READ pending |
| 284 | `backend/tests/Feature/LegacyTransactionIsolationTest.php` | Source/config/build | INVENTORIED — READ pending |
| 285 | `backend/tests/Feature/LoginHistoryTest.php` | Source/config/build | INVENTORIED — READ pending |
| 286 | `backend/tests/Feature/MalwareScanningTest.php` | Source/config/build | INVENTORIED — READ pending |
| 287 | `backend/tests/Feature/MalwareUploadEnforcementTest.php` | Source/config/build | INVENTORIED — READ pending |
| 288 | `backend/tests/Feature/MapManagementSafetyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 289 | `backend/tests/Feature/MapManagementTest.php` | Source/config/build | INVENTORIED — READ pending |
| 290 | `backend/tests/Feature/MfaEnforcementTest.php` | Source/config/build | INVENTORIED — READ pending |
| 291 | `backend/tests/Feature/MfaTest.php` | Source/config/build | INVENTORIED — READ pending |
| 292 | `backend/tests/Feature/MigrationSmokeTest.php` | Source/config/build | INVENTORIED — READ pending |
| 293 | `backend/tests/Feature/MysqlConcurrencyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 294 | `backend/tests/Feature/PaymentConfigurationTest.php` | Source/config/build | INVENTORIED — READ pending |
| 295 | `backend/tests/Feature/PaymentGatewayTest.php` | Source/config/build | INVENTORIED — READ pending |
| 296 | `backend/tests/Feature/PlanLimitEnforcementTest.php` | Source/config/build | INVENTORIED — READ pending |
| 297 | `backend/tests/Feature/ProductionReadinessTest.php` | Source/config/build | INVENTORIED — READ pending |
| 298 | `backend/tests/Feature/PropertyLifecycleTest.php` | Source/config/build | INVENTORIED — READ pending |
| 299 | `backend/tests/Feature/PublicCatalogPrivacyTest.php` | Source/config/build | INVENTORIED — READ pending |
| 300 | `backend/tests/Feature/PublicIndexBoundsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 301 | `backend/tests/Feature/PublicSubscriptionTest.php` | Source/config/build | INVENTORIED — READ pending |
| 302 | `backend/tests/Feature/RateLimitTest.php` | Source/config/build | INVENTORIED — READ pending |
| 303 | `backend/tests/Feature/SavedSearchAlertsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 304 | `backend/tests/Feature/SecurityHeadersTest.php` | Source/config/build | INVENTORIED — READ pending |
| 305 | `backend/tests/Feature/SensitiveMetadataTest.php` | Source/config/build | INVENTORIED — READ pending |
| 306 | `backend/tests/Feature/StorageSecurityTest.php` | Source/config/build | INVENTORIED — READ pending |
| 307 | `backend/tests/Feature/SubscriptionInvoicesTest.php` | Source/config/build | INVENTORIED — READ pending |
| 308 | `backend/tests/Feature/SupportTicketsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 309 | `backend/tests/Feature/WorkspaceApprovalTest.php` | Source/config/build | INVENTORIED — READ pending |
| 310 | `backend/tests/Feature/WorkspaceHardeningTest.php` | Source/config/build | INVENTORIED — READ pending |
| 311 | `backend/tests/Feature/WorkspaceReplaceGuardTest.php` | Source/config/build | INVENTORIED — READ pending |
| 312 | `backend/tests/Feature/WorkspaceRoleGateTest.php` | Source/config/build | INVENTORIED — READ pending |
| 313 | `backend/tests/Feature/WorkspaceTenantMatrixTest.php` | Source/config/build | INVENTORIED — READ pending |
| 314 | `backend/tests/Support/concurrency_request.php` | Source/config/build | INVENTORIED — READ pending |
| 315 | `backend/tests/Support/fake_clamd.php` | Source/config/build | INVENTORIED — READ pending |
| 316 | `backend/tests/TestCase.php` | Source/config/build | INVENTORIED — READ pending |
| 317 | `backend/tests/Unit/ExampleTest.php` | Source/config/build | INVENTORIED — READ pending |
| 318 | `backend/tests/Unit/PermissionsCatalogTest.php` | Source/config/build | INVENTORIED — READ pending |
| 319 | `backend/tests/Unit/RolePermissionsTest.php` | Source/config/build | INVENTORIED — READ pending |
| 320 | `backend/vite.config.js` | Source/config/build | INVENTORIED — READ pending |
| 321 | `docker-compose.ci.yml` | Source/config/build | INVENTORIED — READ pending |
| 322 | `docker-compose.yml` | Source/config/build | INVENTORIED — READ pending |
| 323 | `docker/backend/nginx.conf` | Source/config/build | INVENTORIED — READ pending |
| 324 | `docker/backend/supervisord.conf` | Source/config/build | INVENTORIED — READ pending |
| 325 | `docker/frontend/nginx.conf` | Source/config/build | INVENTORIED — READ pending |
| 326 | `docker/nginx.ci.conf` | Source/config/build | INVENTORIED — READ pending |
| 327 | `docs/API.md` | Documentation | INVENTORIED — READ pending |
| 328 | `docs/AUDIT_LEDGER_HEADER.md` | Documentation | INVENTORIED — READ pending |
| 329 | `docs/BACKUP_RESTORE.md` | Documentation | INVENTORIED — READ pending |
| 330 | `docs/COMPLETE_FILE_AUDIT_LEDGER.md` | Documentation | INVENTORIED — READ pending |
| 331 | `docs/DATABASE.md` | Documentation | INVENTORIED — READ pending |
| 332 | `docs/DEMO_GUIDE.md` | Documentation | INVENTORIED — READ pending |
| 333 | `docs/DEPLOYMENT.md` | Documentation | INVENTORIED — READ pending |
| 334 | `docs/FINAL_COMPLETION_REPORT.md` | Documentation | INVENTORIED — READ pending |
| 335 | `docs/FINAL_ENTERPRISE_AUDIT.md` | Documentation | INVENTORIED — READ pending |
| 336 | `docs/INDEPENDENT_REVIEW_20261127.md` | Documentation | INVENTORIED — READ pending |
| 337 | `docs/PERFORMANCE_AUDIT.md` | Documentation | INVENTORIED — READ pending |
| 338 | `docs/PRODUCTION_READINESS.md` | Documentation | INVENTORIED — READ pending |
| 339 | `docs/RELEASE_CHECKLIST.md` | Documentation | INVENTORIED — READ pending |
| 340 | `docs/SECURITY.md` | Documentation | INVENTORIED — READ pending |
| 341 | `docs/SECURITY_AUDIT.md` | Documentation | INVENTORIED — READ pending |
| 342 | `docs/STATUS.md` | Documentation | INVENTORIED — READ pending |
| 343 | `docs/TEST_EVIDENCE.md` | Documentation | INVENTORIED — READ pending |
| 344 | `docs/openapi.yaml` | Source/config/build | INVENTORIED — READ pending |
| 345 | `documents/01_SYSTEM_ARCHITECTURE.md` | Documentation | INVENTORIED — READ pending |
| 346 | `documents/02_PRD_USER_STORIES.md` | Documentation | INVENTORIED — READ pending |
| 347 | `documents/03_DATABASE_SCHEMA.md` | Documentation | INVENTORIED — READ pending |
| 348 | `documents/04_MODEL_ARCHITECTURE.md` | Documentation | INVENTORIED — READ pending |
| 349 | `documents/05_SEEDERS_FACTORIES.md` | Documentation | INVENTORIED — READ pending |
| 350 | `documents/06_SERVICE_LAYER.md` | Documentation | INVENTORIED — READ pending |
| 351 | `documents/07_VALIDATION_SANITIZATION.md` | Documentation | INVENTORIED — READ pending |
| 352 | `documents/08_AUTH_RBAC.md` | Documentation | INVENTORIED — READ pending |
| 353 | `documents/09_CRUD_CONTROLLERS_API.md` | Documentation | INVENTORIED — READ pending |
| 354 | `documents/10_RESPONSIVE_UI.md` | Documentation | INVENTORIED — READ pending |
| 355 | `documents/11_LIVE_INTERACTIVE_STATES.md` | Documentation | INVENTORIED — READ pending |
| 356 | `documents/12_TOAST_NOTIFICATIONS.md` | Documentation | INVENTORIED — READ pending |
| 357 | `documents/13_SECURITY_HARDENING.md` | Documentation | INVENTORIED — READ pending |
| 358 | `documents/14_ERROR_HANDLING_LOGGING.md` | Documentation | INVENTORIED — READ pending |
| 359 | `documents/15_DEPLOYMENT_CICD.md` | Documentation | INVENTORIED — READ pending |
| 360 | `documents/16_ENTERPRISE_ARCHITECTURE.md` | Documentation | INVENTORIED — READ pending |
| 361 | `frontend/.dockerignore` | Source/config/build | INVENTORIED — READ pending |
| 362 | `frontend/.env.example` | Source/config/build | INVENTORIED — READ pending |
| 363 | `frontend/.gitignore` | Source/config/build | INVENTORIED — READ pending |
| 364 | `frontend/Dockerfile` | Source/config/build | INVENTORIED — READ pending |
| 365 | `frontend/Dockerfile.prod` | Source/config/build | INVENTORIED — READ pending |
| 366 | `frontend/__seo.mjs` | Source/config/build | INVENTORIED — READ pending |
| 367 | `frontend/e2e/admin-pages.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 368 | `frontend/e2e/agency-marketplace.smoke.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 369 | `frontend/e2e/cross-browser-smoke.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 370 | `frontend/e2e/helpers/adminLogin.ts` | Source/config/build | INVENTORIED — READ pending |
| 371 | `frontend/e2e/helpers/isolatedIp.ts` | Source/config/build | INVENTORIED — READ pending |
| 372 | `frontend/e2e/interactive-elements.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 373 | `frontend/e2e/maps.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 374 | `frontend/e2e/public-pages.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 375 | `frontend/e2e/responsive.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 376 | `frontend/e2e/role-based.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 377 | `frontend/e2e/sale-only.smoke.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 378 | `frontend/e2e/security.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 379 | `frontend/e2e/smoke.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 380 | `frontend/e2e/whatsapp.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 381 | `frontend/e2e/workflows.spec.ts` | Source/config/build | INVENTORIED — READ pending |
| 382 | `frontend/index.html` | Source/config/build | INVENTORIED — READ pending |
| 383 | `frontend/nginx.conf` | Source/config/build | INVENTORIED — READ pending |
| 384 | `frontend/package-lock.json` | Source/config/build | INVENTORIED — READ pending |
| 385 | `frontend/package.json` | Source/config/build | INVENTORIED — READ pending |
| 386 | `frontend/playwright.config.ts` | Source/config/build | INVENTORIED — READ pending |
| 387 | `frontend/playwright.cross-browser.config.ts` | Source/config/build | INVENTORIED — READ pending |
| 388 | `frontend/public/favicon.svg` | Source/config/build | INVENTORIED — READ pending |
| 389 | `frontend/public/hero-bg.jpg` | Binary/asset | INVENTORIED — READ pending |
| 390 | `frontend/public/hero-slide-2.jpg` | Binary/asset | INVENTORIED — READ pending |
| 391 | `frontend/public/hero-slide-3.jpg` | Binary/asset | INVENTORIED — READ pending |
| 392 | `frontend/public/hero-slide-4.jpg` | Binary/asset | INVENTORIED — READ pending |
| 393 | `frontend/public/hero-slide-5.jpg` | Binary/asset | INVENTORIED — READ pending |
| 394 | `frontend/public/image.png` | Binary/asset | INVENTORIED — READ pending |
| 395 | `frontend/public/logo.jpg` | Binary/asset | INVENTORIED — READ pending |
| 396 | `frontend/public/manifest.webmanifest` | Source/config/build | INVENTORIED — READ pending |
| 397 | `frontend/public/og-image.jpg` | Binary/asset | INVENTORIED — READ pending |
| 398 | `frontend/public/robots.txt` | Documentation | INVENTORIED — READ pending |
| 399 | `frontend/public/sample-video.mp4` | Binary/asset | INVENTORIED — READ pending |
| 400 | `frontend/public/sw.js` | Source/config/build | INVENTORIED — READ pending |
| 401 | `frontend/scripts/browser-admin-interactions.mts` | Source/config/build | INVENTORIED — READ pending |
| 402 | `frontend/scripts/browser-audit.mts` | Source/config/build | INVENTORIED — READ pending |
| 403 | `frontend/scripts/browser-interactions.mts` | Source/config/build | INVENTORIED — READ pending |
| 404 | `frontend/scripts/check-bundle.mjs` | Source/config/build | INVENTORIED — READ pending |
| 405 | `frontend/scripts/concurrency-booking.mts` | Source/config/build | INVENTORIED — READ pending |
| 406 | `frontend/scripts/legacy-visibility-probe.mts` | Source/config/build | INVENTORIED — READ pending |
| 407 | `frontend/scripts/load-test.mts` | Source/config/build | INVENTORIED — READ pending |
| 408 | `frontend/scripts/mail-flow-e2e.mts` | Source/config/build | INVENTORIED — READ pending |
| 409 | `frontend/scripts/mail-sink.mts` | Source/config/build | INVENTORIED — READ pending |
| 410 | `frontend/scripts/mfa-e2e.mts` | Source/config/build | INVENTORIED — READ pending |
| 411 | `frontend/scripts/perf-snapshot.mts` | Source/config/build | INVENTORIED — READ pending |
| 412 | `frontend/scripts/prerender.mts` | Source/config/build | INVENTORIED — READ pending |
| 413 | `frontend/scripts/qa-audit.mts` | Source/config/build | INVENTORIED — READ pending |
| 414 | `frontend/scripts/qa-contact.mts` | Source/config/build | INVENTORIED — READ pending |
| 415 | `frontend/scripts/qa-theme.mts` | Source/config/build | INVENTORIED — READ pending |
| 416 | `frontend/scripts/role-matrix-e2e.mts` | Source/config/build | INVENTORIED — READ pending |
| 417 | `frontend/scripts/run-tests.mjs` | Source/config/build | INVENTORIED — READ pending |
| 418 | `frontend/scripts/security-probe.mts` | Source/config/build | INVENTORIED — READ pending |
| 419 | `frontend/scripts/sensitive-strings.json` | Source/config/build | INVENTORIED — READ pending |
| 420 | `frontend/scripts/shot-current.mts` | Source/config/build | INVENTORIED — READ pending |
| 421 | `frontend/scripts/shot-mobile-parts.mts` | Source/config/build | INVENTORIED — READ pending |
| 422 | `frontend/scripts/verify-fixes.mts` | Source/config/build | INVENTORIED — READ pending |
| 423 | `frontend/scripts/workflows-e2e.mts` | Source/config/build | INVENTORIED — READ pending |
| 424 | `frontend/src/App.tsx` | Source/config/build | INVENTORIED — READ pending |
| 425 | `frontend/src/components/admin/AdminAgencyApprovals.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 426 | `frontend/src/components/admin/AdminAgencyApprovals.tsx` | Source/config/build | INVENTORIED — READ pending |
| 427 | `frontend/src/components/admin/AdminAgencyDashboard.tsx` | Source/config/build | INVENTORIED — READ pending |
| 428 | `frontend/src/components/admin/AdminAgencyOversight.tsx` | Source/config/build | INVENTORIED — READ pending |
| 429 | `frontend/src/components/admin/AdminAudit.tsx` | Source/config/build | INVENTORIED — READ pending |
| 430 | `frontend/src/components/admin/AdminBuyers.tsx` | Source/config/build | INVENTORIED — READ pending |
| 431 | `frontend/src/components/admin/AdminCMS.tsx` | Source/config/build | INVENTORIED — READ pending |
| 432 | `frontend/src/components/admin/AdminCrmPipeline.tsx` | Source/config/build | INVENTORIED — READ pending |
| 433 | `frontend/src/components/admin/AdminDealers.tsx` | Source/config/build | INVENTORIED — READ pending |
| 434 | `frontend/src/components/admin/AdminDocumentVault.tsx` | Source/config/build | INVENTORIED — READ pending |
| 435 | `frontend/src/components/admin/AdminEnterpriseUi.tsx` | Source/config/build | INVENTORIED — READ pending |
| 436 | `frontend/src/components/admin/AdminFeaturedPromotions.tsx` | Source/config/build | INVENTORIED — READ pending |
| 437 | `frontend/src/components/admin/AdminFileManagement.tsx` | Source/config/build | INVENTORIED — READ pending |
| 438 | `frontend/src/components/admin/AdminInquiries.tsx` | Source/config/build | INVENTORIED — READ pending |
| 439 | `frontend/src/components/admin/AdminLayout.tsx` | Source/config/build | INVENTORIED — READ pending |
| 440 | `frontend/src/components/admin/AdminLocations.tsx` | Source/config/build | INVENTORIED — READ pending |
| 441 | `frontend/src/components/admin/AdminLoginModal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 442 | `frontend/src/components/admin/AdminMapManagement.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 443 | `frontend/src/components/admin/AdminMapManagement.tsx` | Source/config/build | INVENTORIED — READ pending |
| 444 | `frontend/src/components/admin/AdminMediaLibrary.tsx` | Source/config/build | INVENTORIED — READ pending |
| 445 | `frontend/src/components/admin/AdminMediaStudio.tsx` | Source/config/build | INVENTORIED — READ pending |
| 446 | `frontend/src/components/admin/AdminMfaSettings.tsx` | Source/config/build | INVENTORIED — READ pending |
| 447 | `frontend/src/components/admin/AdminModerationDesk.tsx` | Source/config/build | INVENTORIED — READ pending |
| 448 | `frontend/src/components/admin/AdminModuleSkeleton.tsx` | Source/config/build | INVENTORIED — READ pending |
| 449 | `frontend/src/components/admin/AdminNotifications.tsx` | Source/config/build | INVENTORIED — READ pending |
| 450 | `frontend/src/components/admin/AdminOverview.tsx` | Source/config/build | INVENTORIED — READ pending |
| 451 | `frontend/src/components/admin/AdminPlotLocator.tsx` | Source/config/build | INVENTORIED — READ pending |
| 452 | `frontend/src/components/admin/AdminProjects.tsx` | Source/config/build | INVENTORIED — READ pending |
| 453 | `frontend/src/components/admin/AdminProperties.tsx` | Source/config/build | INVENTORIED — READ pending |
| 454 | `frontend/src/components/admin/AdminSecurityRoles.tsx` | Source/config/build | INVENTORIED — READ pending |
| 455 | `frontend/src/components/admin/AdminSellers.tsx` | Source/config/build | INVENTORIED — READ pending |
| 456 | `frontend/src/components/admin/AdminStaffManager.tsx` | Source/config/build | INVENTORIED — READ pending |
| 457 | `frontend/src/components/admin/AdminSubscription.tsx` | Source/config/build | INVENTORIED — READ pending |
| 458 | `frontend/src/components/admin/AdminSupportDesk.tsx` | Source/config/build | INVENTORIED — READ pending |
| 459 | `frontend/src/components/admin/AdminTransactions.tsx` | Source/config/build | INVENTORIED — READ pending |
| 460 | `frontend/src/components/admin/adminFileUpload.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 461 | `frontend/src/components/common/AgencyDetailsModal.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 462 | `frontend/src/components/common/AgencyDetailsModal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 463 | `frontend/src/components/common/ConfirmDialog.tsx` | Source/config/build | INVENTORIED — READ pending |
| 464 | `frontend/src/components/common/EmptyState.tsx` | Source/config/build | INVENTORIED — READ pending |
| 465 | `frontend/src/components/common/ErrorBoundary.tsx` | Source/config/build | INVENTORIED — READ pending |
| 466 | `frontend/src/components/common/FavoriteHeart.tsx` | Source/config/build | INVENTORIED — READ pending |
| 467 | `frontend/src/components/common/Footer.tsx` | Source/config/build | INVENTORIED — READ pending |
| 468 | `frontend/src/components/common/ImageWithFallback.tsx` | Source/config/build | INVENTORIED — READ pending |
| 469 | `frontend/src/components/common/Lightbox.tsx` | Source/config/build | INVENTORIED — READ pending |
| 470 | `frontend/src/components/common/MfaChallengeForm.tsx` | Source/config/build | INVENTORIED — READ pending |
| 471 | `frontend/src/components/common/Navbar.tsx` | Source/config/build | INVENTORIED — READ pending |
| 472 | `frontend/src/components/common/Reveal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 473 | `frontend/src/components/common/StatsCounter.tsx` | Source/config/build | INVENTORIED — READ pending |
| 474 | `frontend/src/components/common/ToastContainer.tsx` | Source/config/build | INVENTORIED — READ pending |
| 475 | `frontend/src/components/common/VideoModal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 476 | `frontend/src/components/common/WhatsAppCta.tsx` | Source/config/build | INVENTORIED — READ pending |
| 477 | `frontend/src/components/common/WhatsAppFloatingBtn.tsx` | Source/config/build | INVENTORIED — READ pending |
| 478 | `frontend/src/components/common/statusBadges.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 479 | `frontend/src/components/common/usePrefersReducedMotion.ts` | Source/config/build | INVENTORIED — READ pending |
| 480 | `frontend/src/components/presentation/DeviceFrame.tsx` | Source/config/build | INVENTORIED — READ pending |
| 481 | `frontend/src/components/presentation/PresentationBar.tsx` | Source/config/build | INVENTORIED — READ pending |
| 482 | `frontend/src/components/public/AboutSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 483 | `frontend/src/components/public/AgenciesDirectoryPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 484 | `frontend/src/components/public/AgenciesSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 485 | `frontend/src/components/public/AgencyAccessPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 486 | `frontend/src/components/public/AgencyProfilePage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 487 | `frontend/src/components/public/AuthPage.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 488 | `frontend/src/components/public/AuthPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 489 | `frontend/src/components/public/AvailableForSaleSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 490 | `frontend/src/components/public/CompareModal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 491 | `frontend/src/components/public/CompareTray.tsx` | Source/config/build | INVENTORIED — READ pending |
| 492 | `frontend/src/components/public/ContactSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 493 | `frontend/src/components/public/CrawlableDetailLinks.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 494 | `frontend/src/components/public/FeaturedProjects.tsx` | Source/config/build | INVENTORIED — READ pending |
| 495 | `frontend/src/components/public/FeaturedProperties.tsx` | Source/config/build | INVENTORIED — READ pending |
| 496 | `frontend/src/components/public/HeroSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 497 | `frontend/src/components/public/MapsPage.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 498 | `frontend/src/components/public/MapsPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 499 | `frontend/src/components/public/MediaGallerySection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 500 | `frontend/src/components/public/NotFoundPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 501 | `frontend/src/components/public/ProjectDetailPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 502 | `frontend/src/components/public/ProjectsListingPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 503 | `frontend/src/components/public/PropertiesListingPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 504 | `frontend/src/components/public/PropertyDetailPage.tsx` | Source/config/build | INVENTORIED — READ pending |
| 505 | `frontend/src/components/public/SaveSearchModal.tsx` | Source/config/build | INVENTORIED — READ pending |
| 506 | `frontend/src/components/public/SavedSearchPanel.tsx` | Source/config/build | INVENTORIED — READ pending |
| 507 | `frontend/src/components/public/Scheme45Guide.tsx` | Source/config/build | INVENTORIED — READ pending |
| 508 | `frontend/src/components/public/ServicesSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 509 | `frontend/src/components/public/TestimonialsSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 510 | `frontend/src/components/public/VideoWalkthroughSection.tsx` | Source/config/build | INVENTORIED — READ pending |
| 511 | `frontend/src/components/public/heroSlider.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 512 | `frontend/src/components/public/mediaVideoFlow.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 513 | `frontend/src/config/appMode.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 514 | `frontend/src/config/appMode.ts` | Source/config/build | INVENTORIED — READ pending |
| 515 | `frontend/src/config/seo.ts` | Source/config/build | INVENTORIED — READ pending |
| 516 | `frontend/src/config/vercel-routes.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 517 | `frontend/src/context/AppContext.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 518 | `frontend/src/context/AppContext.tsx` | Source/config/build | INVENTORIED — READ pending |
| 519 | `frontend/src/data/agencyVideosSeed.ts` | Source/config/build | INVENTORIED — READ pending |
| 520 | `frontend/src/data/erpSeed.empty.ts` | Source/config/build | INVENTORIED — READ pending |
| 521 | `frontend/src/data/erpSeed.ts` | Source/config/build | INVENTORIED — READ pending |
| 522 | `frontend/src/data/publicSeed.ts` | Source/config/build | INVENTORIED — READ pending |
| 523 | `frontend/src/domain/auditLog.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 524 | `frontend/src/domain/auditLog.ts` | Source/config/build | INVENTORIED — READ pending |
| 525 | `frontend/src/domain/authSession.ts` | Source/config/build | INVENTORIED — READ pending |
| 526 | `frontend/src/domain/mediaPolicy.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 527 | `frontend/src/domain/mediaPolicy.ts` | Source/config/build | INVENTORIED — READ pending |
| 528 | `frontend/src/domain/mfa.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 529 | `frontend/src/domain/mfa.ts` | Source/config/build | INVENTORIED — READ pending |
| 530 | `frontend/src/domain/permissions.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 531 | `frontend/src/domain/permissions.ts` | Source/config/build | INVENTORIED — READ pending |
| 532 | `frontend/src/domain/transactions.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 533 | `frontend/src/domain/transactions.ts` | Source/config/build | INVENTORIED — READ pending |
| 534 | `frontend/src/index.css` | Source/config/build | INVENTORIED — READ pending |
| 535 | `frontend/src/main.tsx` | Source/config/build | INVENTORIED — READ pending |
| 536 | `frontend/src/pages/AuthFlowPages.tsx` | Source/config/build | INVENTORIED — READ pending |
| 537 | `frontend/src/services/api.ts` | Source/config/build | INVENTORIED — READ pending |
| 538 | `frontend/src/storage/indexedDb.ts` | Source/config/build | INVENTORIED — READ pending |
| 539 | `frontend/src/types/index.ts` | Source/config/build | INVENTORIED — READ pending |
| 540 | `frontend/src/utils/apiProperty.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 541 | `frontend/src/utils/apiProperty.ts` | Source/config/build | INVENTORIED — READ pending |
| 542 | `frontend/src/utils/documentUpload.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 543 | `frontend/src/utils/documentUpload.ts` | Source/config/build | INVENTORIED — READ pending |
| 544 | `frontend/src/utils/exportCsv.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 545 | `frontend/src/utils/exportCsv.ts` | Source/config/build | INVENTORIED — READ pending |
| 546 | `frontend/src/utils/logic.sales.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 547 | `frontend/src/utils/logic.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 548 | `frontend/src/utils/logic.ts` | Source/config/build | INVENTORIED — READ pending |
| 549 | `frontend/src/utils/marketplaceFeatures.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 550 | `frontend/src/utils/propertyCompare.ts` | Source/config/build | INVENTORIED — READ pending |
| 551 | `frontend/src/utils/reloadGuard.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 552 | `frontend/src/utils/reloadGuard.ts` | Source/config/build | INVENTORIED — READ pending |
| 553 | `frontend/src/utils/savedSearchFilters.ts` | Source/config/build | INVENTORIED — READ pending |
| 554 | `frontend/src/utils/whatsapp.test.ts` | Source/config/build | INVENTORIED — READ pending |
| 555 | `frontend/src/utils/whatsapp.ts` | Source/config/build | INVENTORIED — READ pending |
| 556 | `frontend/tsconfig.json` | Source/config/build | INVENTORIED — READ pending |
| 557 | `frontend/vercel.json` | Source/config/build | INVENTORIED — READ pending |
| 558 | `frontend/vite.config.ts` | Source/config/build | INVENTORIED — READ pending |
| 559 | `metadata.json` | Source/config/build | INVENTORIED — READ pending |
| 560 | `nginx/nginx.conf` | Source/config/build | INVENTORIED — READ pending |
| 561 | `package.json` | Source/config/build | INVENTORIED — READ pending |
| 562 | `scripts/backup.ps1` | Source/config/build | INVENTORIED — READ pending |
| 563 | `scripts/backup.sh` | Source/config/build | INVENTORIED — READ pending |
| 564 | `scripts/restore-drill.ps1` | Source/config/build | INVENTORIED — READ pending |
| 565 | `scripts/wait-for-compose-ready.sh` | Source/config/build | INVENTORIED — READ pending |
| 566 | `vercel.json` | Source/config/build | INVENTORIED — READ pending |
