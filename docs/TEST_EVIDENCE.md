# Test Evidence - Independent Audit 2026-11-23

All results below come from commands actually executed in this workspace or from GitHub's public
Jobs API. No fabricated results, no weakened tests. Passwords are never printed.

**Database honesty:** the local XAMPP server is **MariaDB 10.4.32** (MySQL protocol). MySQL 8 is
authoritative and is verified only in GitHub Actions (`mysql:8.0` service). Local results are not
represented as MySQL 8 evidence.

## Backend

| Command | Result |
|---|---|
| `php artisan migrate --force` (fresh local databases) | Success, all migrations including `properties_public_listing_idx` |
| `php artisan db:seed --force` | Success (Enterprise catalog, Map, EnterpriseDemo in non-production) |
| `php artisan test` | **362 passed / 3054 assertions, 0 failed, exit 0** (437.97 s, local MariaDB) |
| `php -l` on all tracked PHP files | **265 files linted, 0 syntax errors** |
| `composer audit --no-interaction` | `No security vulnerability advisories found`, exit 0 |
| `php scripts/assert-db-driver.php` | Enforces MySQL driver; no SQLite fallback reintroduced |
| GitHub `ci.yml` run #45151908407 (commit `b4b40c9`) | **run conclusion success** - Backend suite (PHP 8.3, MySQL 8), Frontend verify, OpenAPI all success |

## Frontend

| Command | Result |
|---|---|
| `npm run verify` | **exit 0**; TypeScript check, **149/149 unit tests**, build + prerender (sitemap 9 URLs), `check:bundle OK - no private ERP data or demo credentials in public chunks` |
| `npm audit --audit-level=high` | `found 0 vulnerabilities`, exit 0 |
| `npx playwright test --workers=1` (earlier pushed commit) | **87/87 passed, 0 failed (Chromium)** incl. 320/375/414 px widths; only CI/runtime files changed since that run |

## Backup and genuine restore drill

| Command | Result |
|---|---|
| `powershell -File scripts/backup.ps1 -Destination "<path with spaces>" -IncludeVault` | MySQL dump of **58 tables**, sha256 manifest, vault archive, exit 0 |
| `powershell -File scripts/restore-drill.ps1 -BackupDir "<path with spaces>" -Scratch "<path with spaces>"` | **RESTORE DRILL: PASS**, exit 0: sha256 + gzip verified, 58 tables / 95 FKs / 25 populated tables parsed from the dump, unique scratch DB created, full dump imported, exact table set (58), all **256 indexes**, `CHECK TABLE` OK for all tables, all **95 FK orphan checks** clean, exact per-table row counts, 4 sale listings / 0 non-sale, 53 migrations, vault extracted, scratch DB + directory removed and verified gone |
| Corrupt-dump failure path (new copy, spaces) | **exit 1** with checksum failure; no scratch DB and no scratch directory left behind |

## nginx gateway - real validation (nginx 1.27.5 binary, same major/minor as `nginx:1.27-alpine`)

| Check | Result |
|---|---|
| `nginx -t` on `docker/nginx.ci.conf` | syntax OK, test successful, exit 0 |
| `nginx -t` on `nginx/nginx.conf` (production, throwaway self-signed cert) | syntax OK, test successful, exit 0 |
| `nginx -t` on `docker/backend/nginx.conf` (legacy image) | syntax OK, test successful, exit 0 |
| `nginx -t` on `docker/frontend/nginx.conf` (legacy image) | syntax OK, test successful, exit 0 |
| Full CI config started, `OPTIONS /api/auth/login` with `Origin: http://localhost` | **HTTP/1.1 204 No Content**, `Access-Control-Allow-Origin: http://localhost`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, `Access-Control-Max-Age: 3600` |
| Production config started on non-privileged ports; `GET /healthz` | **HTTP/1.1 200 OK** |
| Production config; `GET /api/health` over HTTP | **HTTP/1.1 301** to HTTPS |
| Production config; `OPTIONS` on HTTPS API vhost (correct SNI) | **HTTP/1.1 204** with the same four CORS headers |

This is the direct regression test for Docker CI run #45126719133, which died at startup with
`nginx: [emerg] "add_header" directive is not allowed here`. Real `nginx -t` also exposed a second
hard failure (`fastcgi_next_upstream ... http_502` is invalid; only `http_500|http_503|http_403|
http_404|http_429` are accepted) in both gateways; all six occurrences were fixed.

## Configuration and scripts

| Check | Result |
|---|---|
| `python yaml.safe_load` on both compose files and both workflows | All parse OK |
| `bash -n scripts/wait-for-compose-ready.sh` and extracted smoke-test script | Exit 0 |
| Readiness gate stub tests | all-ready → exit 0 (`9/9`); timeout → exit 1 with `::error` annotation + logs; unknown expectation → exit 2 |
| `docs/openapi.yaml` parse | OpenAPI 3.1.0, 97 paths |
| Active SQLite references in config/workflows/scripts | None (only the "no SQLite fallback" guard and a legacy backup importer) |

## GitHub Actions (public Jobs API; raw logs return HTTP 403 without credentials)

| Run | Job | Status |
|---|---|---|
| [CI #45117225042](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45117225042) | Backend suite (PHP 8.3) | failure - 354 failures, MySQL refused; redundant job removed by this audit |
| same | Backend MySQL 8 / Frontend / OpenAPI | success |
| [CI #45119217795](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45119217795) | all 3 jobs | run conclusion success |
| [Docker CI #45116283434](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45116283434) | Backend image | failure - phpredis missing in readiness container (fixed) |
| [Docker CI #45119217941](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45119217941) | Backend image | failure - Redis numeric cache round-trip (fixed with string sentinel) |
| [Docker CI #45120325512](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45120325512) | Backend image readiness/healthcheck | success; PHPUnit step in `--no-dev` image failed and was removed |
| [CI #45120793219](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45120793219) | all 3 jobs | run conclusion success |
| [Docker CI #45120793292](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45120793292) | image jobs | success; integration failed at readiness gate (no nginx due to invalid config) |
| [Docker CI #45126719133](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45126719133) | Frontend/Backend image | success; integration failed: `add_header` in server-level `if` → nginx never started |
| [CI #45151908407](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45151908407) (commit `b4b40c9`) | all 3 jobs | **run conclusion success** |
| [Docker CI #45151908446](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45151908446) (commit `b4b40c9`) | Frontend image, Backend image | **success** - both images built with real readiness/healthcheck |
| same | Full Stack Integration Test | **success** - gateway, CORS preflight, register, login, `/auth/me` and logout through nginx |
| same | Backup & Restore Drill (MySQL 8) | failure at migrations/seed; captured annotation proved SQLSTATE 1045 from workflow-level `DB_PASSWORD` override (fixed in `8a9781b`) |
| Docker CI #45152755481, #45153053915, #45154239242 | Backup & Restore Drill | successive failures exposed and fixed two more real defects: non-portable `sha256sum` manifest (`1a07a63`) and hidden-file vault verification under pwsh/Linux (`30b7b75`) |
| [Docker CI #45154829075](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45154829075) (commit `30b7b75`) | all four jobs | final verification run; status recorded in the final verdict |

Docker images and the Compose stack cannot be built locally (no Docker CLI/daemon); those results are
from GitHub Actions only and are never reported as local evidence.
