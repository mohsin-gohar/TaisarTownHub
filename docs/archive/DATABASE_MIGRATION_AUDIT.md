# Database and Migration Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Verified

- Full backend suite uses `RefreshDatabase` with an in-memory SQLite database, so the **entire migration set runs from scratch on every test run**. Current full run: 239 passed / 1,955 assertions — clean-install reproducibility is therefore proven for the suite's schema.
- Foreign keys/indexes/uniqueness exercised by existing tests: unique `dealNumber` (`test_database_rejects_a_duplicate_deal_number`), unique agency-membership lookup, soft-delete/restore (`StorageSecurityTest`, `WorkspaceHardeningTest`), cascade/restrict behaviour in finance and document tests.
- Money columns are integer PKR / decimal commission values; derived totals are recomputed from ledger rows, not trusted from the client.
- Seeders are idempotent (`updateOrCreate`/`updateOrInsert`) — `EnterpriseSeeder`, `EnterpriseDemoSeeder` reviewed.
- No production demo data was added by this increment; no destructive migration was run against the user's database.

## Fixed

- The gitignored private vault directory now has a tracked placeholder (`backend/storage/app/private/documents/.gitignore`), so `ProductionCheck` no longer fails on a fresh CI checkout.

## Preserved, not touched

- Pre-existing uncommitted `backend/database/migrations/2026_11_16_144458_create_bookings_table.php` (a bare `bookings` stub) and modified `CODEBASE_AUDIT.md` were left exactly as found. Their origin/intent was not assumed.

## Not testable here

- Upgrade from the user's real production schema: no production backup/restore drill was executed on real data. An upgrade copy of the live schema was not available; only fresh migrations were exercised.
- MySQL-specific `lockForUpdate()`/FK behaviour (test DB is SQLite). Real MySQL concurrency and cascade checks remain an external prerequisite.
- Legacy ownership report: 110 unowned rows remain; no ownership was stamped. Verified mapping requires business approval and a verified backup.
