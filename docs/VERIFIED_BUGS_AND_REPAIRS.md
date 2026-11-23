# Verified Bugs and Repairs — Independent Audit 2026-11-23

Every entry below was verified from source and/or real execution. No claim is made for unverified items.
Baseline: `e419f2a9d82f977bec28abb8cba0945581827cd6` (local `main` == `origin/main`, clean).

## 1. CRITICAL — CI backend job: 354 failures, 8 passed

- **Evidence:** public GitHub Jobs API for run
  [45117225042](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45117225042):
  job `Backend suite (PHP 8.3)` = `failure` (`Run feature suite` failed), while
  `Backend suite on MySQL 8 (integration)` = `success` (362 tests), `Frontend verify` = `success`,
  `OpenAPI spec parses` = `success`. Reported error: MySQL connection refused at 127.0.0.1:3306.
- **Root cause:** `.github/workflows/ci.yml` contained a redundant `backend` job with **no MySQL
  service**, but `backend/phpunit.xml` configures MySQL exclusively (the project is MySQL-only).
- **Fix:** removed the redundant job; the MySQL 8 job is now the single authoritative backend suite.
  No SQLite fallback was introduced; `pdo_sqlite` removed from PHP extensions.
- **Verification:** YAML parses; MySQL job logic unchanged and previously green (362 tests); local
  MySQL suite re-executed (see `docs/TEST_EVIDENCE.md`). CI must be re-run on the pushed commit.

## 2. CRITICAL — Docker backend container never healthy (root cause found)

- **Evidence:** GitHub Jobs API for run
  [45116283434](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45116283434):
  `Build & Test Backend Image` failed at `Wait for backend readiness` after ~3 minutes;
  `Run backend healthcheck` and container tests were skipped; `Full Stack Integration Test` skipped.
  MySQL/Redis/ClamAV readiness, migrations and backend image build all succeeded.
- **Root cause:** `backend/Dockerfile.prod` installed `pdo_mysql` but **no Redis PHP driver** and
  the project does not require `predis/predis`. Compose runs the backend with `CACHE_STORE=redis`,
  `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` and `REDIS_CLIENT` defaults to `phpredis`.
  Therefore `Cache::get('healthcheck')` in `backend/scripts/healthcheck.php` throws
  "Redis extension not installed" on every probe. The container never becomes healthy regardless
  of timeout length; increasing timeouts would only mask it.
- **Fix:**
  - `backend/Dockerfile.prod`: install phpredis via `pecl install redis` + `docker-php-ext-enable redis`
    (build deps removed afterwards).
  - `backend/scripts/healthcheck.php`: real cache **round-trip** (`put` + `get`) and per-dependency
    stderr diagnostics (`database`, `cache`, `storage`) so failures are observable in `docker logs`.
- **Verification / second root cause found by CI re-run #11:** after installing phpredis the image
  built successfully and migrations passed, but the container was STILL unhealthy. Source inspection
  of `Illuminate\Cache\RedisStore` showed Laravel 12 stores numeric values **without serialization**,
  so a `put(1)` / `get()` round-trip returns the string `"1"`; the new strict `!== 1` check failed.
  Fixed by round-tripping a non-numeric sentinel string and comparing as a string. Local healthcheck
  (MySQL + database cache) returns `{"status":"healthy",...,"checks":{"database":"ok","cache":"ok","storage":"ok"}}`
  with exit 0. Docker is not installed locally, so the rebuilt image must be verified by CI.

## 3. HIGH — "Restore drill" never restored anything

- **Root cause:** `scripts/restore-drill.ps1` only decompressed the `.sql.gz` and counted
  `CREATE TABLE` / `INSERT INTO` strings. A corrupt or structurally invalid dump could be reported PASS.
- **Fix:** rewrote it as a genuine isolated drill: sha256 manifest verification, gzip integrity,
  temporary `tth_restore_drill_<stamp>` MySQL database, full `SOURCE` import, comparison of actual
  table count against the dump, row counts for 9 critical tables, named-index verification, real
  `SELECT` queries, vault `tar -tzf` integrity, and cleanup that drops ONLY the temporary database.
- **Verification (real execution):** `RESTORE DRILL: PASS` — 58/58 tables restored, users 5,
  agencies 1, properties 4, dealers 12, inquiries 4, transactions 1, business_records 1, maps 11,
  migrations 53, sale listings query = 4, vault 2 entries, temporary DB dropped. No live DB touched.

## 4. HIGH — Backup script silently ignored DB_PASSWORD from `.env`

- **Root cause:** `scripts/backup.sh` read `DB_NAME/USER/HOST/PORT` from `.env` but used
  `${DB_PASSWORD:-}` from the process environment, producing a dump failure (or root-without-password
  attempt) when DB_PASSWORD existed only in `.env`.
- **Fix:** read `DB_PASSWORD` from `.env` without echoing it; MySQL-only guard added.
- **Verification:** `scripts/backup.ps1` produced a real 58-table dump + manifest + vault archive;
  `backup.sh` logic reviewed (Bash executed on CI hosts, not available as a full run here).

## 5. MEDIUM — Root `Dockerfile.prod` was an unsafe, obsolete production image

- **Root cause:** the root-level multi-stage Dockerfile bundled nginx+supervisord inside the API
  image, installed the SQLite CLI, generated `APP_KEY` **during build** (secret baked into the
  image), and did not match the Compose architecture (`backend/Dockerfile.prod` PHP-FPM behind the
  nginx gateway). Its existence invited accidental misuse.
- **Fix:** clearly marked DEPRECATED / NOT AUTHORITATIVE at the top, removed the SQLite package and
  build-time `key:generate`, and pointed readers to the authoritative images. README updated.
- **Verification:** source review + reference scan (compose and CI reference only the per-service
  Dockerfiles). Not built — no Docker runtime locally.

## 7. HIGH — Docker CI ran PHPUnit inside a `--no-dev` production image

- **Evidence:** Docker CI run
  [45120325512](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45120325512):
  backend readiness and the healthcheck step now **passed** (string-sentinel fix worked), but
  `Run backend tests in container` failed after ~1 second.
- **Root cause:** `backend/Dockerfile.prod` installs dependencies with `composer install --no-dev`,
  while PHPUnit and `nunomaduro/collision` (which provides `artisan test`) are `require-dev` packages.
  The command can never exist in the production image.
- **Fix:** removed the two invalid in-container test steps (`docker-backend` and `docker-integration`);
  added an explanatory comment. Backend tests remain authoritative in `ci.yml` on MySQL 8.
- **Verification:** workflow YAML parses; step removed. CI must be re-run on the pushed commit.

## 8. MEDIUM — Misleading CI comments / obsolete test-driver claims

- **Root cause:** workflow comments still described SQLite `:memory:` tests and `pdo_sqlite`.
- **Fix:** comments and extension lists corrected to MySQL-only; no driver fallback remains.
- **Verification:** repository search shows only the intentional "no SQLite fallback" guard name and
  the legacy backup importer (which only reads historical `.sqlite.gz` migration files).

## 9. CRITICAL - nginx gateway could not start (`add_header` in server-level `if`)

- **Evidence:** Docker CI run
  [45126719133](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45126719133):
  integration failed at the readiness gate because nginx exited with
  `nginx: [emerg] "add_header" directive is not allowed here`. The same illegal block existed in
  `docker/nginx.ci.conf`, `nginx/nginx.conf` and the legacy `docker/backend/nginx.conf`.
- **Root cause:** `add_header` is legal in `http`, `server`, `location` and `if-in-location`, but not
  inside a server-level `if`. The CORS preflight handler put four `add_header` directives there, so
  nginx refused to load the configuration.
- **Fix:** `error_page 418 = @cors_preflight;` + `if ($request_method = 'OPTIONS') { return 418; }`
  + a named `location @cors_preflight` containing the headers and `return 204`. Behaviour is
  preserved; the directive is now in a legal context. Applied to all four tracked nginx configs.
- **Verification:** real `nginx -t` on nginx 1.27.5 passes for all four configs. Starting the full
  CI config and the production config (non-privileged ports) returned **HTTP 204** with
  `Access-Control-Allow-Origin`, `-Methods`, `-Headers` and `-Max-Age` for an `OPTIONS` request.

## 10. HIGH - Invalid `fastcgi_next_upstream` tokens

- **Evidence:** the same real `nginx -t` run initially failed with
  `nginx: [emerg] invalid value "http_502" ...`. `fastcgi_next_upstream` accepts
  `http_500|http_503|http_403|http_404|http_429` (plus `error`, `timeout`, `invalid_header`,
  `non_idempotent`); `http_502`/`http_504` are invalid and nginx refuses to start.
- **Fix:** all six occurrences in the two active gateways replaced with
  `error timeout invalid_header http_500 http_503`. Verified by `nginx -t` again (exit 0).

## 11. MEDIUM - Invalid 204 response headers and deprecated HTTP/2 syntax

- Removed `add_header Content-Type`/`Content-Length` from the 204 preflight: a 204 must not carry a
  body and `Content-Length` is invalid there.
- Replaced `listen 443 ssl http2;` with `listen 443 ssl; http2 on;` in the production config;
  the deprecation warnings disappeared from `nginx -t`.
- Pinned `docker-compose.yml` to `nginx:1.27-alpine` (was floating `nginx:alpine`) so CI validates
  the same tag that runs in production.

## 12. HIGH - Readiness gate could report success while the stack was broken

- **Root cause:** the old `scripts/wait-for-compose-ready.sh` counted lines containing "healthy"
  and, after 30 iterations, silently exited 0 even when required services were missing. That is why
  the nginx startup failure above surfaced only as a vague timeout instead of a hard failure.
- **Fix:** rewrote the gate to read `docker compose ps -a` fields (`State`, `Health`, `ExitCode`),
  check an explicit named list, fail at a defined deadline with a GitHub `::error` annotation, and
  dump `compose ps` plus logs of not-ready services.
- **Verification:** stub-driven tests: all-ready → exit 0 (`9/9`), timeout → exit 1 with TIMEOUT +
  annotation + logs, unknown expectation → exit 2. `bash -n` clean.

## 13. HIGH - MySQL "healthy" before the app database exists

- **Root cause A (Compose, real defect):** `mysqladmin ping -h localhost` can pass against MySQL's
  temporary init-time socket server before `MYSQL_DATABASE`/`MYSQL_USER` exist, so `migrate` could
  start against a missing database. Fixed by a real TCP `SELECT 1` as the app user with a 30 s start
  period; the same hardening was applied to the backup-restore CI service plus a defensive wait.
- **Root cause B (the actual CI failure, proven by annotation):** Docker CI run
  [45151908446](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45151908446) and the
  next two runs failed at `Fresh migrations and seed data`. A temporary failure annotation exposed
  `SQLSTATE[HY000] [1045] Access denied for user 'root'@'172.18.0.1' (using password: YES)`.
  The workflow-level `env: DB_PASSWORD: test-password` (correct for the Compose integration job's
  `taisar` user) was inherited by the backup-restore job; Laravel's `env()` reads the process
  environment **before** `.env`, so PHP ignored the generated `.env` root password. The `mysql` CLI
  wait step used the literal root password and passed, which is why the failure surfaced in Laravel.
- **Fix:** job-level `env: {DB_USERNAME: root, DB_PASSWORD: test-root-password}` overrides the
  workflow value (commit `8a9781b`). Service healthcheck/wait hardening and failure annotations were
  retained; they made the diagnosis possible.
- **Verification:** local reproduction of the seed step on a throwaway database passed; final CI
  result to be recorded in `docs/TEST_EVIDENCE.md`.

## 14. MEDIUM - Integration smoke tests did not prove authentication or image parity

- Image jobs and the integration job run on different GitHub runners, so images built earlier were
  not available; the job also used `up -d` with implicit builds. The smoke test only curled public
  endpoints, so a broken gateway/auth path could pass.
- **Fix:** integration job rebuilds both cached images itself, starts with `--no-build`, validates
  all four nginx configs first, seeds the production-safe enterprise catalog, curls every request
  through nginx, checks the OPTIONS preflight regression, and performs a real register → token →
  `/auth/me` → logout → login → `/auth/me` flow.
- **Verification:** Docker CI run
  [45151908446](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45151908446):
  frontend image, backend image and **Full Stack Integration Test all success**.

## 15. MEDIUM - Restore drill client resolution was unreliable

- The drill used a bare `mysql` name in `ProcessStartInfo`, which does not reliably search `PATH` on
  every platform. It now resolves the client to an absolute path, fails fast when missing, and
  still falls back to the known XAMPP location locally.
- **Verification:** fresh backup + drill PASS (58 tables, 256 indexes, 95 FK checks, exact rows,
  vault, verified cleanup) and a corrupt-backup run exit 1 with **no** leftover scratch DB/directory.

## 16. Documentation integrity

- A parallel audit added documents claiming "CONDITIONALLY READY", "706 files individually
  reviewed", "Tested" performance latencies and 1684 backend assertions. None were supported by
  executed evidence. They were preserved with explicit correction notices pointing to the
  authoritative verdict and evidence; the scratch `write_perf.py` generator was removed.
- `docs/COMPLETE_FILE_AUDIT_LEDGER.md` was regenerated mechanically from `git ls-files` with the
  READ / TESTED / INVENTORIED / HISTORICAL / ASSET / BLOCKED vocabulary; no file is claimed to be
  line-by-line reviewed unless it actually was.

## 17. MEDIUM - Portable backup manifest format

- **Evidence:** local reproduction of the exact CI combination (`bash scripts/backup.sh` followed
  by `restore-drill.ps1`, paths with spaces) failed with
  `RESTORE DRILL: FAIL - manifest has no checksum for 'tth_db_...sql.gz'`.
- **Root cause:** `sha256sum` on Git Bash/Windows emits `<hash> *<filename>` for binary files;
  `backup.sh` wrote that output verbatim, while the drill's regex expected `<hash>  <filename>`.
- **Fix:** `backup.sh` now writes the manifest explicitly with `printf '%s  %s\n'`; the drill also
  accepts an optional leading `*`. CI's drill step now emits a failure annotation with its own
  captured output.
- **Verification:** after the fix, the exact shell backup + drill combination **passed locally**:
  58 tables, 256 indexes, 95 FK checks, exact row counts, vault extraction and verified cleanup,
  with a clean `<hash>  <filename>` manifest. Final CI result recorded in `docs/TEST_EVIDENCE.md`.

## 18. MEDIUM - Vault verification failed on a hidden file under PowerShell 7/Linux

- **Evidence:** Docker CI #20 annotation:
  `RESTORE DRILL: FAIL - Could not find item /tmp/.../vault/documents/.gitignore.`
  All database checks had passed; only the hidden `.gitignore` entry from the Linux-built tar failed.
- **Root cause:** per-entry `Get-Item`/`Test-Path` verification of hidden files behaved differently
  under pwsh 7 on Linux.
- **Fix:** build the extracted inventory once with `Get-ChildItem -Force -Recurse`, compare every
  tar entry against that list, and compute file count/bytes from the same list.
- **Verification:** local shell-backup + drill PASS (58 tables, 256 indexes, 95 FKs, 1 vault file,
  14 bytes, cleanup verified). Final CI result recorded in `docs/TEST_EVIDENCE.md`.

## Verified-good controls (no change required)

- AgencyContext resolves tenancy from memberships, never request bodies; `DocumentController::stream`
  applies agency scoping, permission checks, sensitive-document clearance, scan-status gate and
  audit logging before streaming from the private disk.
- Mass-assignment surface: no `$request->all()` persisted to models; writes use `only([...])`.
- Google OAuth uses one-time cached state + browser nonce; suspended/inactive accounts rejected.
- Public-marketplace composite index has a MySQL-safe explicit name; fresh MySQL migration succeeds.
- Redis container healthcheck has the password in its environment (so its `redis-cli -a` probe works).
