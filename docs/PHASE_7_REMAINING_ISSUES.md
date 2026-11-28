# PHASE 7 — Remaining Issues

**Date:** 2026-11-28  
**Branch:** `audit/phase-7-agency-erp-workflows` @ `1a899db`

---

## Open Phase 7 defects (current)

| # | Severity | Area | Test | Error | Status |
|---|----------|------|------|-------|--------|
| 1 | HIGH | CRM / leads / inquiries | `CrmLeadsTest::test_csv_export_respects_agent_record_scope` | CSV export did not contain expected agent-scoped rows (`Export Mine`) | OPEN |
| 2 | HIGH | CRM / leads / inquiries | `CrmLeadsTest::test_csv_export_neutralises_formula_injection` | CSV formula-injection neutralization assertion failed | OPEN |
| 3 | HIGH | Finance / transactions | `TransactionFinanceDataIsolationTest::test_finance_lists_and_reports_are_isolated_between_two_agencies` | Cross-agency deal not returned in agency B's list | OPEN |

## Severity rationale

- CSV export scope failure implies agency lead scoping may be bypassed or mis-scoped in export path.
- Formula-injection neutralization failure implies CSV sanitization gap.
- Finance cross-agency isolation failure implies tenant boundary regression in list/report path.

## Recommended next actions

1. Fix CRM CSV export scoping (use agency-scoped query, not unscoped).
2. Add/verify formula-injection neutralization in CSV writer.
3. Fix finance cross-agency isolation (ensure list/report query scoped by resolved agency).
4. Re-run affected suites; re-verify.

---

## Status

**OPEN** — 3 Phase 7 defects remain. Phase 7 not yet `VERIFIED`.

---

## Known non-Phase-7 / pre-existing issues (context)

- Backend pre-existing failures (outside Phase 7 scope, from Phase 1-6 gap run): `DatabaseSchemaContractTest`, `FinanceIsolationTest`, `LegacyUnownedDataHandlingTest`, `AdminStaffSubscriptionTest`, plus 3 others — 7 total at last full run.

---

## Notes / overlaps

- `FinanceController.php`, `InquiryController.php`, `AgencyStaffController.php` (OpenCode) treated read-only; no edits made.
