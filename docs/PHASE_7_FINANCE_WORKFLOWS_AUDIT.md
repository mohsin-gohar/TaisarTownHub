# Phase 7 — Agency Finance & Transaction Workflows Audit

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Findings

| # | Severity | File / Method | Finding | Fix | Regression test | Result |
|---|----------|---------------|---------|-----|-----------------|--------|
| 1 | — | `FinanceController::findScoped()` | Agency-scoped with platform-admin fallback. Non-admin without agency context → 404. | N/A | `FinanceIsolationTest` | PASS |
| 2 | — | `FinanceController::storePayment()` | `DB::transaction` with balance validation (payment ≤ remaining). Integer minor-unit comparison. | N/A | `FinanceLifecycleTest` | PASS |
| 3 | P1 | `FinanceController::storeSchedules()` | Fixed in critical repairs: wrapped in `DB::transaction` with parent transaction lock (`lockForUpdate()`), retry=3. Partial failures cannot leave incomplete schedules. | Added transaction + lock. | `FinanceLifecycleTest` | PASS |
| 4 | — | `FinanceController::void()` | `DB::transaction`, prevents payments on voided transactions. | N/A | `FinanceLifecycleTest` | PASS |
| 5 | — | `FinanceController::destroyPayment()` | Recorded payments immutable (409) — refunds reverse instead of delete. | N/A | `FinanceLifecycleTest` | PASS |
| 6 | — | Commission calculations | Integer minor-unit (paisa) comparisons, round to `decimal(14,2)`. | N/A | `FinanceLifecycleTest` | PASS |
| 7 | — | `FinanceController::reports()` | Permission-scoped (`finance.view` or `view_transactions`), cross-tenant data excluded. | N/A | `FinanceReportsTest` | PASS |
| 8 | — | `FinanceController::expenses*()` | Agency-scoped expenses with audit logging. | N/A | `FinanceLifecycleTest` | PASS |

## Financial invariants verified

- Payments cannot exceed permitted transaction balance — VERIFIED
- Refunds cannot exceed collected amounts — VERIFIED
- Voided transactions reject additional payments — VERIFIED
- Duplicate requests cannot create duplicate entries — VERIFIED (DB constraints + unique checks)
- Concurrent requests preserve ledger consistency — VERIFIED (row locks)
- Agency A cannot read/change Agency B's financial data — VERIFIED (`findScoped()`)
- Unauthorized users cannot access finance reports — VERIFIED

## Business model

Cash/manual-ledger approach preserved — no payment gateways, SDKs or paid integrations introduced.

## Verdict

No unresolved P0/P1 defects. All finance invariants enforced server-side.
