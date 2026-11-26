# File Inspection Coverage

Branch: `fix/workspace-transaction-lifecycle-20261126` (HEAD `5fbf3e0`)
Date: 2026-11-26

## Scope method
- Application source and configuration were inspected; generated/vendor/build output was excluded.
- Coverage is reported as concrete counts, not "100%" claims.

## Inventory (project-owned files)

| Area | Files | Inspected | Notes |
|---|---:|---:|---|
| Backend app (PHP) | 118 | 118 (targeted deep reads) | Controllers, models, middleware, services, requests, support |
| Backend database (migrations) | 54 | 54 | Schema + indexes + constraints reviewed |
| Backend routes | 4 | 4 | `api_definitions.php` dual registration reviewed |
| Backend config | 15 | 15 | phpunit, database, cors, sanctum, etc. |
| Backend tests | 68 | 68 (run, not line-read) | Executed via `php artisan test` |
| Frontend src (TS/TSX) | 130 | targeted | Domain, components, pages, contexts, hooks, api clients |
| Frontend e2e | 15 | run (Playwright) | Public pages, maps, interactive elements |
| Docker / compose | 4 + compose | reviewed | docker-compose.yml, Dockerfiles |
| Nginx | 1 | reviewed | nginx/nginx.conf |
| CI workflows | 2 | reviewed | ci.yml, docker-ci.yml |
| Docs | 8 + new audit | reviewed | status, deployment, security, backup, api |

## Deeply inspected files (changed or audited in detail)
- `backend/app/Http/Controllers/Api/DealerController.php` (undefined `$effectiveCommissionType`)
- `backend/app/Http/Controllers/Api/FinanceController.php` (updateCommission agency context)
- `backend/app/Http/Controllers/Api/WorkspaceController.php` (platform-user agency resolution, canWrite gates, enforce* guards)
- `backend/tests/Feature/FinanceIsolationTest.php`, `FinanceLifecycleTest.php`, `WorkspaceHardeningTest.php`, `BookingConcurrencyTest.php`
- `frontend/src/domain/transactions.ts` (`collectedForTx` legacy-advance handling)
- `docker-compose.yml`, `nginx/nginx.conf`, `.github/workflows/ci.yml`, `.github/workflows/docker-ci.yml`

## Not fully line-read (excluded as generated/non-source)
- `backend/vendor/`, `frontend/node_modules/`, `frontend/dist/`, `frontend/audit/`, build outputs, binaries, lockfiles.

## Verification evidence
- Backend: `php artisan test` → 540 passed, 0 failed, 4527 assertions, exit 0 (see TESTING_REPORT.md).
- Frontend: `npm run verify` → 173 tests, 0 failures, tsc clean, build + 29 prerendered pages, exit 0.
- Browser: Playwright chromium smoke (cross-browser-smoke 7, maps 5, interactive-elements 9) all passed at desktop viewport.