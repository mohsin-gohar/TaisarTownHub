# Bug Register and Fixes

**Branch:** `fix/complete-audit-20261117` · **Baseline:** `09a15b9` · **Date:** 2026-11-17

| ID | Severity | File / method | Root cause | Fix | Regression test | Result |
|---|---|---|---|---|---|---|
| A | Critical | `AgencyStaffController::acceptInvitation` | Invite role `owner` mapped to global `users.role='admin'` | Base role `dealer`; owner-only invite; locked single-use claim; honest `is_platform` | `AdminStaffSubscriptionTest` ×3 | PASS |
| B | Critical | `TransactionController::index/store` | No tenant scope; NULL-tenant rows; foreign refs; unsafe deal numbers | Agency scope, server-bound ownership, scoped `exists`, locked number allocation | `LegacyTransactionIsolationTest` ×5 | PASS |
| C | High | `DealerFilesController` | No permissions, agency binding, quota or PII protection | `AuthorizesApi` gates, dealer identity binding, `dealer_files`/`storage_mb` quota, PII projection, audit rows | `DealerFilesAuthorizationTest` ×7 | PASS |
| D | High | `EnsureMfaVerified` / `AuthController::mfaVerify` | User-wide cache key, no invalidation | `App\Support\MfaVerification` token-scoped stamps + revocation hooks | `MfaEnforcementTest` ×4 | PASS |
| E | High | `FinanceController::index/show`, `Transaction` | Undefined `businessRecord` relation → 500 with data | Added `hasOne` relation | `test_finance_index_and_show_work_when_the_agency_has_deals` | PASS |
| F | High | `FinanceController::store` | Wrong column names lost property/dealer attribution | `propertyId`/`dealerId` + scoped validation | `test_finance_store_persists_property_and_dealer_references` | PASS |
| G | High | `FinanceController::void` | No permission check | `requireAny(void_transactions, …)` | `test_void_requires_an_explicit_permission` | PASS |
| H | High | `.gitignore` / CI | Missing private docs dir on fresh checkout | Tracked placeholder + parent unignore | `ProductionCheck` via full suite | PASS |
| I | Medium | `FinanceController::update` | Arbitrary `dealerId` | `exists` + membership agency check | `test_finance_update_rejects_a_foreign_agency_dealer` | PASS |
| J | Medium | `.github/workflows/ci.yml` | PyYAML assumed preinstalled | Explicit `pip install pyyaml` | Local config review | PASS (static) |
| K | Medium | `Dockerfile.prod`, compose | Dead backend stage, no scheduler/healthchecks | Documented, not changed | — | BLOCKED (no Docker) |
| L | Medium | `EnsureMfaVerified::mfaRequiredFor` | Global MFA policy key unseeded, role whitelist ignores agency roles | Documented, not changed | — | OPEN (policy decision) |
| M | Low | Legacy vs finance deal visibility | Divergent dealer self-scope | Documented, not changed | — | OPEN (policy decision) |

## Second-round CI repairs (run 44059650335)

| ID | Severity | File / method | Root cause | Fix | Result |
|---|---|---|---|---|---|
| N | High | `.github/workflows/ci.yml` "Wait for MySQL" | `php -r "$pdo = ..."` — shell expands `$pdo` → step exit 255, suite skipped | `backend/scripts/wait-for-db.php` (env credentials, 30 retries) + `assert-db-driver.php` guard | PASS locally (MySQL 8.4.9) |
| O | High | `frontend/package.json` test script | Quoted `"src/**/*.test.ts"` glob not expanded on fresh checkout/CI → `Could not find` | `frontend/scripts/run-tests.mjs` discovers files with `fs` and runs tsx with explicit paths | 133 tests pass on fresh clone |
| P | High | `frontend/src/services/api.ts` public catalog | Empty `cnicMasked: ''` placeholder in public-safe catalog flagged by bundle guard (`cnicMasked` private token) | Removed the unused private ERP field from public catalog | `check:bundle` OK on fresh clone |
| Q | Medium | `.github/workflows/ci.yml` frontend job | Node 20 pinned while Vite 8/rolldown need `^20.19 || >=22.12` | Pinned `node-version: '22'`, renamed job | Local Node 22 verify green |

## Test-count progression

Baseline 216 / 1,866 assertions → **243 / 1,974 assertions** after all fixes (both MySQL 8.4.9 and SQLite).
New backend test files: `LegacyTransactionIsolationTest`, `DealerFilesAuthorizationTest`, `MfaEnforcementTest`; added cases to `AdminStaffSubscriptionTest`, `FinanceIsolationTest`.

## Test integrity

No test was deleted, skipped or weakened. `BookingConcurrencyTest`'s fixture was extended with the `finance.create_transaction` permission that the new authorization gate legitimately requires; all five of its original assertions remain unchanged and pass.
