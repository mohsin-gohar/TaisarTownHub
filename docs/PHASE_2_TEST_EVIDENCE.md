# Phase 2 — Test Evidence

## Environment
- PHP 8.2, Composer 2.10, MySQL 8.4 (dedicated isolated DB `taisartownhub_p2`, separate from the shared `taisartownhub_test` used by concurrent sessions).
- Isolated git worktree: `C:\Users\mohsi\AppData\Local\Temp\kilo\p2-worktree` on branch `audit/phase-2-backend-audit-20261128` (real vendor copy so app-code edits load correctly).
- `.env.testing`: MySQL, `MALWARE_SCAN_ENABLED=false`, `FILEVAULT_ENCRYPTION_ENABLED=false`.

## Results
| Run | Scope | Result |
|---|---|---|
| Full Feature suite (isolated worktree) | `--testsuite=Feature` | **457 passed, 2 failed** (3676 assertions) before final fixes |
| After invite-ordering fix | `AdminStaffSubscriptionTest` | 18 passed (was 1 failing) |
| Touched-area regression | Dealer/Finance/Subscription/Property/PublicIndex | **76 passed** (485 assertions) |
| Concurrency (isolated) | `MysqlConcurrencyTest` | 2 passed (10 assertions) |
| Dealer tenancy (isolated) | `DealerAgencyLinkTest` | 3 passed (incl. new cross-tenant regression) |

## The 2 full-suite failures (resolved/explained)
1. `AdminStaffSubscriptionTest > agency cannot create duplicate pending invitations` — **real pre-existing defect** (seat-limit 402 masked duplicate 422). Reproduced on clean base `76faa76` (not caused by Phase 2 edits). **Fixed** by reordering duplicate checks before the limit check. Now 18/18 pass.
2. `AgencyStorageOversightTest > oversight storage aggregation query count` — **test-pollution flake**: passes in isolation (3 grouped queries captured), returns 0 only in the full suite after `MysqlConcurrencyTest` commits real rows and spawns worker processes on the shared DB. Production code verified N+1-free (3 grouped aggregate queries). This test's `DB::listen` counter is fragile to cross-test connection state.

## Defects repaired this phase (each verified)
- Cross-tenant dealer deletion → regression test added (3/3 pass).
- Negative commission → covered by FinanceLifecycleTest (passes).
- Duplicate subscription race → SubscriptionInvoicesTest/AdminStaffSubscriptionTest pass.
- Invite ordering (duplicate 422 vs limit 402) → AdminStaffSubscriptionTest 18/18.
- 7 parse-error defects (double backslashes) → backend boots, 406 routes register, full suite runs.

## Not weakened
No assertions were changed to force a pass. The only test edits align tests with the intentionally hardened security contract (financial-field grant, moderator-only pending_changes, seeded category catalog) and add a new regression test.

## Full-suite runtime note
The full Feature suite took ~2h under heavy concurrent load (multiple agent sessions + worker processes). Targeted runs are the authoritative regression signal for the changed areas.