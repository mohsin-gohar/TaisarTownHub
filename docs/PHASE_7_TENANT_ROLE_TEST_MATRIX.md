# PHASE 7.8 — End-to-End Role and Tenant Test Matrix

**Date:** 2026-11-28  
**Branch:** `audit/phase-7-agency-erp-workflows` @ `1a899db`

---

## Matrix

| Persona | Required verification | Result |
|---------|----------------------|--------|
| Agency Owner | Full authorized agency workflows | Via agency/lifecycle suites ✅ |
| Agency Manager | Delegated permissions only | `AgencyMemberAccessRestrictionsTest` ✅ |
| Operator | Assigned operational workflows only | Covered via staff/operator sub-tests ✅ |
| Ordinary Member | Explicitly granted permissions only | `AgencyMemberAccessRestrictionsTest` ✅ |
| Platform Admin | Platform-only capabilities | `CrossAgencyCapabilitiesTest` ✅ |
| Agency A user | Cannot access Agency B private data | `AgencyDataSeparationTest` ✅, `TransactionFinanceDataIsolationTest` ❌, `CrmLeadsTest` ❌ |
| Agency B user | Cannot access Agency A private data | mirrored via Agency A/B separation ✅ |

## Cross-tenant negative coverage

- Agency A ↔ Agency B workspace isolation: ✅ GREEN (agency, document, capability suites).
- Finance cross-agency isolation: ❌ FAIL (1).
- CRM cross-agency export: ❌ FAIL (2 CSV).

## Status

**PARTIALLY GREEN** — cross-tenant negative tests present and passing in most suites; 3 genuine failures remain.

## Notes / overlaps

- `FinanceController.php`, `InquiryController.php`, `AgencyStaffController.php` (OpenCode) treated read-only.
