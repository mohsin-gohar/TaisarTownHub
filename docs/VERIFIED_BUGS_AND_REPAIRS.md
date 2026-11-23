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

## 6. MEDIUM — Misleading CI comments / obsolete test-driver claims

- **Root cause:** workflow comments still described SQLite `:memory:` tests and `pdo_sqlite`.
- **Fix:** comments and extension lists corrected to MySQL-only; no driver fallback remains.
- **Verification:** repository search shows only the intentional "no SQLite fallback" guard name and
  the legacy backup importer (which only reads historical `.sqlite.gz` migration files).

## Verified-good controls (no change required)

- AgencyContext resolves tenancy from memberships, never request bodies; `DocumentController::stream`
  applies agency scoping, permission checks, sensitive-document clearance, scan-status gate and
  audit logging before streaming from the private disk.
- Mass-assignment surface: no `$request->all()` persisted to models; writes use `only([...])`.
- Google OAuth uses one-time cached state + browser nonce; suspended/inactive accounts rejected.
- Public-marketplace composite index has a MySQL-safe explicit name; fresh MySQL migration succeeds.
- Redis container healthcheck has the password in its environment (so its `redis-cli -a` probe works).
