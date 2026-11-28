# Phase 7 — CRM, Leads & Inquiries Audit

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Findings

| # | Severity | File / Method | Finding | Fix | Regression test | Result |
|---|----------|---------------|---------|-----|-----------------|--------|
| 1 | P1 | `InquiryController::findScopedForAgent()/index()` | Fixed in critical repairs: non-platform users without agency context can no longer execute unscoped tenant queries. | Added `isCrossAgency()` guard + 403 on missing agency context. | `InquiryTenantIsolationTest` | PASS |
| 2 | — | `LeadController::findScoped()` | Agency-scoped (`where('agency_id',$agencyId)`) with agent-level restriction: agents see only own/unassigned leads. | N/A | `CrmLeadsTest` | PASS |
| 3 | — | `LeadController::assign()` | Validates target agent belongs to same agency before assignment. | N/A | `CrmLeadsTest` | PASS |
| 4 | — | `LeadController::export()` | CSV export uses same scoping as index; permission-gated (`crm.export`). | N/A | `CrmLeadsTest` | PASS |
| 5 | — | `LeadController::storeActivity()` | Lead activities created with agency_id, scoped to parent lead. | N/A | `CrmLeadsTest` | PASS |
| 6 | — | `InquiryController::store()` | Public inquiry creation rate-limited (`throttle:api.public`), property-scoped agency attachment server-side. | N/A | `InquiryTest` | PASS |

## Cross-tenant negative tests

- Agency A cannot read/update/delete Agency B's leads — VERIFIED (`LeadController::findScoped()` agency filter)
- Agency A cannot read Agency B's inquiries — VERIFIED (critical repair guard)
- Unauthorized lead assignment → 403/404 — VERIFIED
- Unauthorized CSV export → 403 — VERIFIED

## Verdict

No unresolved P0/P1 defects. All tenant scoping enforced by backend.
