# Phase 8 — Test Evidence

Branch: `audit/phase-8-super-admin-operations`
Base: `origin/main` @ `1a899dbd6375dbbd381e5d6985532158242d7632`
Date: 2026-11-29

## Environment

- Dedicated worktree: `C:/Users/mohsi/AppData/Local/Temp/opencode/phase8-worktree`
- Backend deps: fresh `composer install` in worktree (a junction to the main repo's vendor was removed because Composer's `$baseDir` resolved via realpath to the main repo, loading the wrong `App\` classes — important lesson for worktree testing)
- Frontend deps: fresh `npm ci`
- Test DB: `taisartownhub_phase8` (MySQL/MariaDB, port 3306), isolated from other agents' `taisartownhub_test`
- `.env` copied from main repo (gitignored, not committed)

## Backend (PHPUnit)

| Command (all with `DB_DATABASE=taisartownhub_phase8`) | Result | Exit |
|-------------------------------------------------------|--------|------|
| `php artisan test --filter=Phase8SuperAdminAuthorizationTest` | 6 passed, 38 assertions | 0 |
| `php artisan test --filter=Phase8PaymentReconciliationTest` | 3 passed, 15 assertions | 0 |
| `php artisan test --filter=CrmLeadsTest` | 12 passed, 63 assertions | 0 |
| `php artisan test --filter=TransactionFinanceDataIsolationTest` | 3 passed, 59 assertions | 0 |
| `php artisan test --filter="MalwareScanningTest\|..."` (Phase 7 follow-up) | previously 62 passed | 0 |
| `php artisan test` (full) | 495 passed, 10 failed, 5192 assertions | 2 |
| Baseline (changes stashed, same 7 files) | 10 failed, 50 passed, 874 assertions | 2 |

Full log: `C:/Users/mohsi/AppData/Local/Temp/opencode/phase8-full.txt`; baseline: `phase8-baseline.txt`.

### 10 full-suite failures (reproduced identically on baseline)

| Test | Cause | Disposition |
|------|-------|-------------|
| AdminStaffSubscriptionTest::subscription_plan_creation... | `'5000.00'` vs `5000` precision | EXTERNAL_PREEXISTING |
| AdminStaffSubscriptionTest::dashboard_separates... | FK `transactions_property_fk` (synthetic propertyId) | EXTERNAL_PREEXISTING |
| BookingConcurrencyTest (2 tests) | FK `transactions_property_fk` | EXTERNAL_PREEXISTING |
| DatabaseSchemaContractTest::all_model_fillable... | schema contract assertion | EXTERNAL_PREEXISTING |
| FinanceIsolationTest::platform_super_admin_can_update_a_commission... | 404 vs 200 | EXTERNAL_PREEXISTING |
| FinanceReportsTest (2 tests) | FK `transactions_property_fk` | EXTERNAL_PREEXISTING |
| LegacyTransactionIsolationTest::legacy_index_ignores_foreign_dealer... | FK `transactions_dealer_fk` | EXTERNAL_PREEXISTING |
| LegacyUnownedDataHandlingTest::read_only_legacy_ownership_report... | assertion | EXTERNAL_PREEXISTING |

These match PR #37's documented "7 remaining pre-existing unrelated" failures (count differs by environment). Not introduced by Phase 8 changes; not weakened/skipped.

## Frontend

| Command | Result | Exit |
|---------|--------|------|
| `npm run verify` (lint `tsc --noEmit` + 160 tests + build + prerender + `check:bundle`) | tests 160, fail 0; 29 pages prerendered; bundle check OK | 0 |

Log: `C:/Users/mohsi/AppData/Local/Temp/opencode/phase8-frontend.txt`.

## Security

| Command | Result |
|---------|--------|
| `composer audit --format=plain` | No security vulnerability advisories found |
| `npm audit --audit-level=high` | found 0 vulnerabilities |
| `git diff --check` | Clean |

## Browser E2E

**NOT TESTED.** No live frontend+backend server session with Playwright interaction was run in this phase. Static inspection only (`App.tsx` admin gating, `AdminCMS.tsx`, `AdminLayout.tsx`, etc.). No browser screenshots are substituted for interaction tests.

## Other NOT TESTED / BLOCKED

- Docker image builds / compose smoke (no daemon)
- ClamAV live daemon (fake-clamd tests only)
- TLS / DNS / SMTP / third-party keys (owner-provisioned)
- Lighthouse re-measurement (no staging run)
- Systematic N+1 profiling beyond Phase 6/7 documented findings
