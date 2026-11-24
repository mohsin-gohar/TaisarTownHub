# Test Verification Report — TaisarTownHub

**HEAD:** `28c32edb7bd9f6267e5a58bb97239b371c4b1084`
**Date:** 2026-11-20

## 1. Backend PHP

### `php artisan test` (full suite)
- Result: **320 passed, 2587 assertions, 0 failed** on the authoritative clean isolated run (`backend/.screenshots/full-backend-test-final5.log`).
- Earlier same-day reruns with concurrent DB/browser activity encountered SQLite `malformed` failures and one local-storage-state failure; the final clean isolated run is the authoritative backend result.
- Log tail confirms `PASS` for auth, storage, featured, OAuth, finance, workspace, booking, catalog, and full backend suites.

### Targeted backend rerun after latest sale-only hardening
Command:
```powershell
php artisan test --filter="AgencyController|FeaturedAgencyPromotionTest|AgencyStorageOversafetyTest|GoogleOAuthTest"
```
Corrected filter:
```powershell
php artisan test --filter="AgencyController|FeaturedAgencyPromotionTest|AgencyStorageOversightTest|GoogleOAuthTest"
```
Result: **35 passed, 267 assertions, 0 failed**.

### `php artisan migrate:status`
- Output: all migrations shown as `[1] Ran`; includes `2026_11_20_092340_add_google_oauth_to_users_table`.

### `php artisan migrate --pretend`
- Output: `INFO Nothing to migrate.` After backup restore, all migrations were applied to the restored DB.

### `php artisan app:production-check --json`
- Result: `summary.pass = 20`, `summary.warn = 1`, `summary.fail = 0`.
- Warnings: expected local-only APP_ENV, localhost CORS, and live-server HTTPS suggestion.

### `composer validate`
- Result: `./composer.json is valid, but with a few warnings` (exact version constraints on `stripe/stripe-php`).

### `python3 -c "import yaml; yaml.safe_load(open('docs/openapi.yaml', encoding='utf-8')); print('openapi.yaml YAML OK')"`
- Result: `openapi.yaml YAML OK`.

### `php artisan optimize`
- Result: cached config/events/routes/views successfully.

### `php artisan route:list`
- Result: verified route inventory; representative routes include Google OAuth callback/exchange/redirect, public `/api/properties`, agency profile, `/api/agencies/{id}`, `/api/agencies/featured`, `/api/agency/documents/storage-usage`, and admin featured/oversight routes.

## 2. Frontend

### `npm run verify`
- Result: **PASS**.
- Includes `npm run lint` (`tsc --noEmit`), `npm run test` (unit tests), Vite build + SEO prerender, and `npm run check:bundle`.
- Build output: `dist/assets/AdminFeaturedPromotions-B-S5YTaq.js` changed from the earlier paid-era chunk; bundle check OK.

### `npm run lint`
- Result: exit 0, no output.

### `npm run test`
- Result: passed; see unit-test output in `frontend/src/...test` suites.

### `npm run e2e` / Playwright commands

- `npx playwright test --workers=1 --timeout=60000 e2e/sale-only.smoke.spec.ts`
  - Result: **5/5 passed** (log `e2e-saleonly2.log`).
- `npx playwright test --workers=1 --timeout=60000 e2e/public-pages.spec.ts e2e/sale-only.smoke.spec.ts e2e/interactive-elements.spec.ts`
  - Result: 26 passed in initial run; one transient sale-only API request timeout under parallel load.
  - Isolated rerun: sale-only 5/5 passed.
- `npx playwright test --workers=1 --timeout=60000 e2e/interactive-elements.spec.ts e2e/agency-marketplace.smoke.spec.ts`
  - Result: **14/14 passed** (`e2e-batch2.log`).
- `npx playwright test --workers=1 --timeout=60000 e2e/security.spec.ts`
  - Result: **7/7 passed** (`e2e-security.log`).
- `npx playwright test --workers=1 --timeout=120000 e2e/responsive.spec.ts`
  - Result: 6/7 initially; desktop 1280px failed with a Playwright artifact-cleanup `ENOENT`, not an app overflow.
  - Isolated rerun: `npx playwright test e2e/responsive.spec.ts --grep "desktop 1280px"` → **1/1 passed** (`e2e-responsive-1280.log`).
  - Aggregate responsive result: **7/7 passed**.
- `npx playwright test --workers=1 --timeout=180000 e2e/role-based.spec.ts e2e/admin-pages.spec.ts`
  - Result: 10 passed, 13 failed due environment: API logins return 422/500 and Playwright dev server is intermittently unavailable (`ERR_CONNECTION_REFUSED`).
  - This is not claimed as a product code failure; role/admin browser QA remains blocked/unverified in this environment.

### Public API probes
- `Invoke-WebRequest -Uri http://127.0.0.1:8080/api/properties?purpose=sale -Method GET -UseBasicParsing` → 200, JSON array, 5,339 bytes.
- `Invoke-WebRequest -Uri http://127.0.0.1:8080/api/auth/google/redirect` without nonce → 422 validation error for `nonce` (expected).
- Backend server on 127.0.0.1:8080 is running; port checks confirm 8080 is reachable, 3000/4173 are not.

## 3. Infrastructure feasibility
- Docker: not installed.
- MySQL: binaries exist at `C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe` and `mysql.exe`; service process exists, but port 3306 is not reachable (`Test-NetConnection` false; `mysql -u root -h 127.0.0.1 -e "SELECT VERSION()"` → error 2003).
- MySQL integration: not run; use `php scripts/wait-for-db.php` and `php scripts/assert-db-driver.php` when MySQL service is available.
- MySQL backup/restore drill: not run. Existing SQLite backups are present under `backend/storage/app/backups/`.
- Load/stress: not run.
- Live Google OAuth E2E: blocked because `config/services.google.client_id` and `client_secret` are null locally.
