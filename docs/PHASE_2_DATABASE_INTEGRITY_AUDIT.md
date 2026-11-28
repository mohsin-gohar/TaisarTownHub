# Phase 2 — Database Integrity Audit

Scope: migrations (55 files), schema (58 tables), transactions/concurrency on critical writes, FK consistency, financial calculations.

## Schema verification
- Fresh migrate on isolated DB (`taisartownhub_p2`): 55 migrations applied, 58 tables, `business_records.agency_id` present, `properties.pending_changes` longtext nullable. `migrate:fresh` and `migrate` both succeed on a clean DB.
- `MigrationSmokeTest`: fresh migrations create the core schema (passes).

## Defects repaired in Phase 2
1. **CRITICAL — duplicate subscription race** (`AgencyOnboarding::ensureStarterSubscription`): check-then-create without lock. Fixed with `DB::transaction` + `lockForUpdate()` on the agency row.

## Concurrency findings (remaining, evidence-backed)
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | AgencyPromotionService::promote | AgencyPromotionService.php:126,134 | CRITICAL | Duplicate-placement exists() check outside the transaction |
| 2 | SecureFileStore::move | SecureFileStore.php:51-56 | CRITICAL | Decrypt→encrypt→delete without transaction/lock |
| 3 | AgencyPromotionService::reorder | AgencyPromotionService.php:252 | HIGH | Rows read without lockForUpdate inside transaction |
| 4 | AgencyPromotionService::expireDue | AgencyPromotionService.php:271,276 | HIGH | get() outside transaction |
| 5 | FinanceController::storeSchedules | FinanceController.php:837-890 | MEDIUM | Loop of updateOrCreate without transaction |
| 6 | InquiryController dual-write | InquiryController.php:226-315 | MEDIUM | Inquiry + BusinessRecord without transaction |

## Verified safe
- `MysqlConcurrencyTest` (2 tests): invitation cannot be claimed twice concurrently; concurrent deal allocations get unique numbers (both pass in isolation). Confirms DB-level uniqueness/locking works for those flows.
- `PaymentController`: locked invoice updates + amount reconciliation; webhook signature verification.
- `DealerFilesController::sell`, `AgencyStaffController` quota checks: locked rows.
- Financial calculations use integer/decimal casts; refunds cannot exceed collected; payments cannot exceed server limits or be negative (FinanceLifecycleTest).

## Remaining
Items 1-6 documented in PHASE_2_REMAINING_ISSUES. Financial integrity and tenant isolation were NOT weakened to satisfy tests.