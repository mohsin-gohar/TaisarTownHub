# Phase 2 — Request Validation Audit

No `FormRequest` classes; all validation is inline `$request->validate()`.

## Defects repaired in Phase 2
1. **MEDIUM — negative commission** (`FinanceController::storeCommission`, line 968): `amount` lacked `min:0`. Fixed to `numeric|min:0|max:999999999999`.

## Confirmed findings
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | FinanceController::storeCommission | FinanceController.php:968 | MEDIUM | Negative amount — FIXED |
| 2 | WorkspaceController::sanitizeRow | WorkspaceController.php:402-453 | MEDIUM | Nested JSON collection rows get only size/depth/UTF-8/money coercion, no per-field type/enum/required validation |
| 3 | PropertyController::store/update | PropertyController.php:281,401 | LOW | `features` array has `max:40` but no `features.*` item rules (images.* is validated) |
| 4 | DealerController::store | DealerController.php:94 | LOW | `avatar` `nullable|string` with no `max` (unbounded) |
| 5 | PropertyController::update status | PropertyController.php:390 | LOW | `'status' => 'nullable|max:50'` lacks `string` type (max applies to array size) |
| 6 | AgencyVideoController::store | AgencyVideoController.php:67,74 | LOW | `agencyId` no `exists` rule (resolved manually); `tags` array unbounded |
| 7 | TransactionController vs FinanceController | TransactionController.php:84 / FinanceController.php:155 | LOW | `transactionType` enums differ (`Sale,Purchase,Lease,Transfer` vs `Sale,Rent,Lease,Transfer`) |

## Cross-agency / ownership validation (verified)
- `agencyId` is never trusted from the client for agency-scoped writes; `requireAgency`/`agencyId()` derive it from membership. Platform `agencyId` is validated `exists:agencies,id`.
- `assignedDealerId` validated via `assertDealerInAgency`; `dealer_id`/`user_id` beneficiaries validated `dealerBelongsToAgency`.
- File uploads validate MIME/extension server-side (DocumentController/MediaController/MapController).

## Remaining
Items 2-7 documented in PHASE_2_REMAINING_ISSUES.