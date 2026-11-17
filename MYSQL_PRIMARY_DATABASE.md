# MySQL 8 Primary Database — Verification

**Branch:** `main` · **Date:** 2026-11-17

## Decision

MySQL 8 is the primary database for development and production. SQLite is used
only for lightweight, fast automated tests (`phpunit.xml` default
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Credentials always come from
environment variables / `.env` — nothing is hardcoded. `backend/.env.example`
already defaults to `DB_CONNECTION=mysql`.

## Evidence (real MySQL, not a claim)

- A disposable MySQL **8.4.9** instance was initialized in an isolated data
  directory on port 3310. The user's existing MariaDB database
  (`F:\Xampp\mysql\data\taisartownhub`) was never touched.
- Driver probe confirmed `PROBE_DRIVER=mysql` when env vars override phpunit.xml.
- Full migrations ran clean on MySQL 8 (`migrate:fresh` per RefreshDatabase).
- **Full backend suite on MySQL 8.4.9: 243 passed / 1,974 assertions.**
- **Full backend suite on SQLite fallback: 243 passed / 1,974 assertions.**
- Two-process concurrency tests (`MysqlConcurrencyTest`) run real parallel
  HTTP workers against MySQL:
  - invitation acceptance → exactly one `200`, one `422`, one membership,
    one accepted invitation, two tokens total (4 assertions).
  - two concurrent deal creations → both `201`, unique deal numbers, 2 rows
    (6 assertions).
  - Workers set `innodb_lock_wait_timeout=5` so lock waits fail fast instead
    of hanging CI; on SQLite the same tests fall back to sequential assertions.
- Worker: `backend/tests/Support/concurrency_request.php` boots the real app
  and dispatches through the HTTP kernel; DB credentials are inherited from env.

## CI

`.github/workflows/ci.yml` now has an `integration-mysql` job:
`services: mysql:8.0`, health check, `DB_*` env vars, `wait for MySQL` step and
the full `php artisan test` suite against it. Workflow YAML parses locally
(`yaml.safe_load`: jobs `backend`, `integration-mysql`, `frontend`, `contract`).

## Notes

- MySQL 8.0 (CI service image) vs 8.4 (local probe) — both are MySQL 8 line;
  schema/transaction behaviour is compatible.
- No database was reset except the disposable `tth_audit` database created for
  this verification.
