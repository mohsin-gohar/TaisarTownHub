# Finance Integrity Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Confirmed and fixed

| ID | File / method | Root cause | Fix + test |
|---|---|---|---|
| E | `FinanceController::index/show` via `Transaction` model | `with('businessRecord')` eager-loaded a relation that never existed; worked only while agency lists were empty, 500 with real rows | Added `Transaction::businessRecord()` (`hasOne`, scoped to `collection=transactions`). `test_finance_index_and_show_work_when_the_agency_has_deals` |
| F | `FinanceController::store` | Wrote `property_id`/`dealer_id`, which are not columns/fillable keys — property and dealer attribution silently discarded | Writes `propertyId`/`dealerId`; agency-scoped `exists` validation. `test_finance_store_persists_property_and_dealer_references` |
| G | `FinanceController::void` | No permission gate: any approved workspace member could void a deal | `requireAny(void_transactions, finance.update_transaction, update_transactions)`. `test_void_requires_an_explicit_permission` |
| I | `FinanceController::update` | Arbitrary `dealerId` accepted without existence/agency check | `exists:dealers,id` + active-membership agency check. `test_finance_update_rejects_a_foreign_agency_dealer` |
| B | `TransactionController::store` | NULL-tenant rows, foreign property/dealer accepted, unsafe deal-number generation | Server-side `agency_id`/`created_by`, scoped existence, locked transaction + unique-index retry. `LegacyTransactionIsolationTest` (5 tests) |

## Already sound (re-verified)

- `FinanceController::store`, `approve`, `storePayment` use `DB::transaction` + `lockForUpdate`; overpayment guard present.
- Deal numbers have a DB unique index; enterprise generation uses a locked read plus bounded retry.
- Commission accrual/payment/cancellation and derived ledger totals are computed from real rows.
- Agency financial reports are permission-gated and exclude foreign-agency rows (`FinanceReportsTest`).

## Known gaps (documented, not fixed)

- `destroyPayment` and `update` are non-atomic check-then-act operations; concurrent multi-process requests could produce stale derived totals. Tests are serial against SQLite, so this is not reproducible here.
- `transactionType` enum differs between enterprise and legacy APIs (Sale/Rent vs Sale/Purchase) — LOW, no silent change made.
- Dealer visibility policy for deals differs between the two APIs — needs a business decision.

## Full suite result

239 backend tests passed / 1,955 assertions, including all finance isolation, lifecycle, report and concurrency tests.
