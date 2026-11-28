# Phase 2 — API Response Consistency Audit

## Findings
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | PropertyController::index | PropertyController.php:146-178 | MEDIUM | Paginated envelope `{success,properties,pagination}` only when `page` present; otherwise raw array |
| 2 | Dealer/Project/AgencyVideo | DealerController.php:51,62; ProjectController.php:33-43; AgencyVideoController.php:38-51 | MEDIUM | Raw arrays/objects with no `success` wrapper |
| 3 | Error envelopes | PropertyController.php:201,209,893,909; LeadController.php:370,381; FinanceController.php:166-169,311-314; InquiryController.php:127-132 | MEDIUM | Mix of `abort(4xx)` default errors, `{success:false,message}`, `{message,errors}`, and `{success:false,code,message,errors}` |
| 4 | FinanceController::index | FinanceController.php:114-124 | MEDIUM | Returns same rows under both `transactions` and `data` keys (ambiguous) |
| 5 | PropertyController::destroy | PropertyController.php:523 | LOW | `['message'=>...]` with no `success` key |

## Positive
- No internal exception/SQL leakage: catch blocks log `get_class($e)` only and return generic messages (PropertyController.php:931-933,956-958,1003-1005; PaymentController.php:121-132,461-469).
- Validation errors use Laravel's `{message,errors}` consistently.
- Pagination (where used) follows Laravel `paginate()` shape.

## Remaining
Documented in PHASE_2_REMAINING_ISSUES. A unified response envelope is a recommended refactor but out of scope for a repair pass (would touch many endpoints and risk breaking frontend consumers).