# Test Evidence — Independent Audit 2026-11-23

All commands executed on the local workspace at baseline `e419f2a`. No fabricated results.
Passwords are never printed.

## Backend (MySQL 8 only)

| Command | Result |
|---|---|
| `php artisan migrate --force` (fresh `taisartownhub` + `taisartownhub_test`) | Success — all migrations including `properties_public_listing_idx` |
| `php artisan db:seed --force` | Success — DemoStaff, Enterprise, Map (11 records), EnterpriseDemo seeders |
| `php artisan test` | **362 passed / 3054 assertions, 0 failed** |
| `php scripts/healthcheck.php` | `{"status":"healthy",...,"checks":{"database":"ok","cache":"ok","storage":"ok"}}`, exit 0 |
| `php -l` on all changed PHP files | No syntax errors detected |
| `composer audit` | No security vulnerability advisories found (exit 0) |
| `php scripts/assert-db-driver.php` (CI job) | Passes on MySQL; would fail on any other driver |

## Frontend

| Command | Result |
|---|---|
| `npm run verify` (tsc + unit tests + build/prerender + bundle check) | **EXIT 0**; `tests 149`, `pass 149`, `fail 0`; sitemap 9 URLs; `[check:bundle] OK — no private ERP data or demo credentials in public chunks` |
| `npm audit --audit-level=high` | 0 vulnerabilities (exit 0) |

## Browser (real API calls, MySQL 8)

| Command | Result |
|---|---|
| `npx playwright test --workers=1` (Chromium) | **84 passed, 0 failed (6.5 min), EXIT 0** |
| Coverage | Guest/public pages, all admin roles, security/MFA, tenant/workspace, maps, sale-only marketplace, responsive 360/390/430/768/1024/1280/1440px, workflows |

## Backup and genuine restore

| Command | Result |
|---|---|
| `powershell -File scripts/backup.ps1 -Destination <tmp> -IncludeVault` | MySQL dump of 58 tables + sha256 manifest + vault archive, exit 0 |
| `powershell -File scripts/restore-drill.ps1 -BackupDir <tmp>` | `RESTORE DRILL: PASS` — sha256 + gzip verified, isolated `tth_restore_drill_*` database created, full dump imported, **58/58 tables**, row counts (users 5, agencies 1, properties 4, dealers 12, inquiries 4, transactions 1, business_records 1, maps 11, migrations 53), `properties_public_listing_idx` present, sale query = 4, vault 2 entries, temp DB dropped, exit 0 |

## Configuration/infrastructure checks

| Check | Result |
|---|---|
| Python `yaml.safe_load` on both compose files and both workflows | All parse OK |
| `bash -n scripts/wait-for-compose-ready.sh` | Exit 0 |
| Readiness gate stub tests | All-healthy → exit 0 (`9/9 required services`); unhealthy/starting → exit 1 with TIMEOUT + logs |
| `docs/openapi.yaml` parse | OpenAPI 3.1.0, 97 paths |
| Active SQLite references in config/workflows/scripts | None (only the "no SQLite fallback" guard and legacy backup importer) |

## GitHub Actions (public Jobs API, read via `api.github.com`)

| Run | Job | Status |
|---|---|---|
| [CI #45117225042](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45117225042) | Backend suite (PHP 8.3) | **failure** (354 failures, MySQL refused) — redundant job removed by this audit |
| same | Backend suite on MySQL 8 (integration) | **success** |
| same | Frontend verify (Node 22) | **success** |
| same | OpenAPI spec parses | **success** |
| [Docker CI #45116283434](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45116283434) | Build & Test Frontend Image | **success** |
| same | Build & Test Backend Image | **failure** — `Wait for backend readiness` timed out after ~3 min (root cause: missing phpredis driver, fixed) |
| same | Full Stack Integration Test | **skipped** (upstream job failed) |

Raw log download for these jobs returned HTTP 403 without credentials; job/step conclusions above
come from the public Jobs API. Docker builds/Compose startup were NOT runnable locally (no Docker CLI).
