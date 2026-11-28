# Phase 7 — Property & Media Lifecycle Audit

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Findings

| # | Severity | File / Method | Finding | Root cause | Fix | Regression test | Result |
|---|----------|---------------|---------|-----------|-----|-----------------|--------|
| 1 | — | `PropertyController::serializeForScope()` | Sensitive fields `purchasePricePKR`, `sellingPricePKR`, `internalNotes` gated behind `finance.view`; `pending_changes` gated behind moderation permission. | N/A | N/A | `PublicCatalogPrivacyTest` | PASS |
| 2 | — | `PropertyController::publicPropertyPayload()` | Explicit allowlist — no accidental exposure of private/internal data. | N/A | N/A | `PublicCatalogPrivacyTest` | PASS |
| 3 | — | `PropertyController::index()` | Agency scope via `forAgency()`, public scope via `publiclyVisible()` + sale-only filter. Cross-agency property IDs blocked. | N/A | N/A | `PropertyLifecycleTest` | PASS |
| 4 | — | `PropertyController::approve()/reject()/submit()` | Moderation transitions in `DB::transaction`, status history audited via `recordTransition()`. | N/A | N/A | `PropertyLifecycleTest` | PASS |
| 5 | — | `MediaController::sell()` | Wrapped in `DB::transaction` with `lockForUpdate()`, status re-checked inside transaction — prevents double-sale under concurrency. | N/A | N/A | `MediaSalesTest` | PASS |
| 6 | P2 | `MapController::reorder()` | Individual `Map::where('id',$id)->update()` inside loop (line 429-430) — N+1 pattern. Admin-only, small dataset. | Loop with per-row update. | Not fixed — low impact, avoiding unnecessary refactoring per Phase 7 guidance. | N/A | DOCUMENTED |
| 7 | — | `MediaController::store()/replace()` | Upload failure cleanup via try/finally, DB transaction for metadata. | N/A | N/A | `MediaUploadTest` | PASS |

## Public API exposure check

Verified: no `purchasePricePKR`, `sellingPricePKR`, `internalNotes`, `pending_changes`, moderation state, or owner-only data in public payload.

## Verdict

No unresolved P0/P1 defects. One P2 N+1 documented (admin-only, low impact).
