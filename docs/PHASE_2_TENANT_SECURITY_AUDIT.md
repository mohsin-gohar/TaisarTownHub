# Phase 2 — Multi-Tenant Security Audit

Tenant model: `agency_id` columns + `AgencyContext` middleware (resolves from current memberships) + `AuthorizesApi` (scopes permission checks). Legacy/unowned records (`agency_id = NULL`) are reachable only by platform users via `LegacyTenancyResolver` (read-only, never guesses).

## Defects repaired in Phase 2
1. **CRITICAL — cross-tenant dealer deletion** (`DealerController::destroy`): an agency manager could delete any agency's dealer. Fixed + regression test (`test_agency_scoped_publisher_cannot_delete_another_agencys_dealer`).

## Adversarial test evidence
- `WorkspaceTenantMatrixTest` (15 tests): Agency A cannot read/modify/delete Agency B's workspace collections; reserved keys stripped; legacy NULL rows hidden from members; replace is agency-scoped.
- `LegacyTenancyResolverTest`: ambiguous/evidence-free rows stay null; no guessing.
- `FinanceIsolationTest`, `LegacyTransactionIsolationTest`: finance/transaction data isolated per agency.
- `PropertyLifecycleTest`: `agent cannot manage another agencys listing` (403); public detail hides private fields.
- New: cross-tenant dealer delete now 404 + row preserved.

## Confirmed findings (remaining)
| # | Location | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | InquiryController::findScopedForAgent | InquiryController.php:321-340 | HIGH | When `agencyId()` is null, no `agency_id` filter — a non-platform holder of `crm.update_lead`/`crm.delete_lead` without agency context could mutate/delete another agency's inquiry (public/unassigned leads have null assigned_agent_id) |
| 2 | InquiryController::index | InquiryController.php:35-48 | MEDIUM | No `agency_id` filter when agencyId null and not `mine` — reads every agency's inquiries (CNIC masked) |

## Verified isolation (no action)
WorkspaceController (agency-scoped collections, legacy NULL hidden), FinanceController/TransactionController (findScoped), DocumentController/MediaController (scoped delivery + 404-not-403), LeadController, PropertyController (assertCanManage). Background jobs respect tenant ownership (no cross-tenant job fan-out found).

## Remaining
InquiryController null-agency gap (items 1-2) documented in PHASE_2_REMAINING_ISSUES as HIGH follow-up.