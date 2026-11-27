# Historical File Ledger Path Reconciliation

**Comparison baseline:** `main` at `4baf396fb3074bac134dac9c8bdc40ba2da67bf0`  
**Historical ledger:** `docs/COMPLETE_FILE_AUDIT_LEDGER.md` (origin commit `6c92d52`)  
**Current tree:** 566 tracked blobs, 642 total tree entries.

> This resolves stale path references as repository-layout changes, migrations, directory placeholders, or items with no direct replacement. It does not claim the replacement files have been fully code-reviewed.

| Historical path | Current path / disposition | Classification | Confidence / note |
|---|---|---|---|
| `frontend/src/components/public/HeroSlider.tsx` | `frontend/src/components/public/HeroSection.tsx`; regression test `frontend/src/components/public/heroSlider.test.ts` | Renamed/refactored | High — current app imports HeroSection and a hero slider test exists |
| `frontend/src/components/public/CrawlableDetailLinks.tsx` | `frontend/src/components/public/CrawlableDetailLinks.test.ts`, `PropertyDetailPage.tsx`, `AgenciesDirectoryPage.tsx` | Refactored; no same-name component | Medium — crawlable links behavior is covered/tested in current page structure |
| `frontend/src/components/common/Sidebar.tsx` | `frontend/src/components/admin/AdminLayout.tsx` | Integrated into admin shell | Medium — no standalone Sidebar file in current tree; admin navigation is part of layout |
| `frontend/src/components/common/Breadcrumb.tsx` | `frontend/src/config/seo.ts` and runtime SEO/breadcrumb JSON-LD in `frontend/src/App.tsx` | Behavior integrated; no UI component equivalent found | Medium — metadata breadcrumb behavior exists; no standalone visual breadcrumb component |
| `frontend/src/components/common/StatusBadges.tsx` | `frontend/src/components/common/statusBadges.test.ts` and status renderers embedded in current feature components | No standalone component equivalent found | Low/medium — test file survives, but dedicated component file does not |
| `frontend/src/components/admin/adminFileUpload.tsx` | `frontend/src/components/admin/AdminFileManagement.tsx`, `AdminDocumentVault.tsx`, `adminFileUpload.test.ts` | Split/refactored | High — current admin file/vault modules and upload regression test exist |
| `frontend/config/appMode.ts` | `frontend/src/config/appMode.ts` | Moved | High |
| `frontend/config/seo.ts` | `frontend/src/config/seo.ts` | Moved | High |
| `frontend/services/api.ts` | `frontend/src/services/api.ts` | Moved | High |
| `frontend/src/pages/_app.tsx` | `frontend/src/main.tsx`, `frontend/src/App.tsx` | Next.js to Vite/React app bootstrap refactor | High |
| `frontend/src/pages/_document.tsx` | `frontend/index.html`, `frontend/src/main.tsx` | Framework bootstrap/document refactor | High |
| `frontend/src/pages/index.tsx` | `frontend/src/App.tsx`, `frontend/src/components/public/HeroSection.tsx` | Home route refactored into app shell/components | High |
| `frontend/src/pages/properties.tsx` | `frontend/src/components/public/PropertiesListingPage.tsx` | Moved/refactored | High |
| `frontend/src/pages/agencies.tsx` | `frontend/src/components/public/AgenciesDirectoryPage.tsx` | Moved/refactored | High |
| `frontend/src/pages/property-detail.tsx` | `frontend/src/components/public/PropertyDetailPage.tsx` | Moved/refactored | High |
| `frontend/src/pages/admin.tsx` | `frontend/src/components/admin/AdminLayout.tsx` and admin modules | Refactored into role-aware admin shell | High |
| `frontend/src/pages/login.tsx` | `frontend/src/components/public/AuthPage.tsx` | Consolidated auth route | High |
| `frontend/src/pages/register.tsx` | `frontend/src/components/public/AuthPage.tsx` | Consolidated auth route | High |
| `frontend/src/pages/reset-password.tsx` | `frontend/src/pages/AuthFlowPages.tsx` | Consolidated auth flow page | High |
| `frontend/src/pages/verify-email.tsx` | `frontend/src/pages/AuthFlowPages.tsx` | Consolidated auth flow page | High |
| `backend/tests/Unit/` | `backend/tests/Unit/ExampleTest.php`, `PermissionsCatalogTest.php`, `RolePermissionsTest.php` | Directory marker, not a file | High — current directory is populated |
| `backend/tests/Feature/` | `backend/tests/Feature/*.php` | Directory marker, not a file | High — current directory is populated |
| `backend/database/migrations/2026_11_02_145317_create_transactions_table.php` | `backend/database/migrations/2026_11_02_145318_create_transactions_table.php` | Renamed/re-timestamped migration | High — same migration purpose and adjacent timestamp |
| `backend/database/migrations/2026_11_02_145318_create_projects_table.php` | `backend/database/migrations/2026_11_02_145319_create_projects_table.php` | Renamed/re-timestamped migration | High — same migration purpose and adjacent timestamp |
| `backend/database/migrations/2026_11_03_110000_add_soft_deletes_to_agency_videos.php` | `backend/database/migrations/2026_11_03_120000_add_soft_deletes_to_agency_videos.php` | Renamed/re-timestamped migration | High — same migration purpose and later timestamp |
| `backend/database/seeders/` | `backend/database/seeders/DatabaseSeeder.php` plus current seeder classes | Directory marker, not a file | High |
| `backend/.env.testing` | No direct tracked equivalent; CI injects test environment via workflow env and writes a minimal `.env`; PHPUnit config is `backend/phpunit.xml` | Intentionally absent / environment supplied by CI | High — no test secrets should be committed |
| `backend/config/` | `backend/config/*.php` | Directory marker, not a file | High |
| `frontend/config/` | `frontend/src/config/*.ts` | Directory moved under source tree | High |
| `backend/scripts/` | `backend/scripts/assert-db-driver.php`, `healthcheck.php`, `wait-for-db.php` | Directory marker, not a file | High |
| `frontend/scripts/` | `frontend/scripts/*.mjs` and `*.mts` | Directory marker, not a file | High |

## Summary
- 31 stale exact-path references have been classified.
- 3 migration filenames have direct purpose-matched current names with updated timestamps.
- 20 frontend source paths have been moved, consolidated, or refactored.
- 6 directory references are directory markers rather than missing file artifacts.
- `.env.testing` has no committed replacement by design; CI environment is defined in workflow configuration and `phpunit.xml`.
- The old `Breadcrumb.tsx` and `StatusBadges.tsx` names have no dedicated same-purpose standalone component in the current tree; behavior appears integrated in page/feature modules and needs UI-specific follow-up if a visual component is a product requirement.

## Audit boundary
This mapping is path/layout reconciliation only. The actual content of every replacement file still needs the Phase 2/5 source and UI review. No files were deleted or renamed by this audit.
