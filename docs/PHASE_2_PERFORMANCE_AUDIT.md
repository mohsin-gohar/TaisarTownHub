# Phase 2 — Performance Audit

## Findings
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | AgencyPromotionService::reorderCurrent/reorder/expireDue | AgencyPromotionService.php:257,336,277 | MEDIUM | N+1 per-row updates inside transaction (whereKey()->update() in a loop) |
| 2 | AgencyPromotionService::stats | AgencyPromotionService.php:96-115 | MEDIUM | Five count() queries instead of one aggregate |
| 3 | AgencyController::current | AgencyController.php:304-311 | LOW | Six count() queries instead of withCount() |
| 4 | SecureFileStore::storeUpload/response | SecureFileStore.php:33,125 | MEDIUM | Entire file loaded into memory (no streaming) — memory pressure on large files |

## Verified optimizations (positive)
- `LocationController` and `AdminAgencyOversightController` use grouped aggregate subqueries (no N+1) — verified by `PublicIndexBoundsTest` and `AgencyStorageOversightTest` (query count constant as data grows).
- `PropertyController::index` public scope uses a scalar subquery for promoted-agency ordering (no per-row query).
- Pagination caps public list endpoints (`PublicIndexBoundsTest`: dealers/videos/projects bounded, `?limit` clamped).
- Eager loading used on list endpoints (agency.activePromotion, category/sector relations).

## Caching
- No application-level cache layer in use; `SystemSetting::get` reads settings. Acceptable for current scale; caching is a future optimization, not a defect.

## Remaining
Items 1-4 documented in PHASE_2_REMAINING_ISSUES. No N+1 on the hot public paths; the promotion-service loops are the main remaining inefficiency.