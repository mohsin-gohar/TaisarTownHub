# Phase 7 — Agency Onboarding & Account Lifecycle Audit

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Findings

| # | Severity | File / Method | Finding | Root cause | Fix | Regression test | Result |
|---|----------|---------------|---------|-----------|-----|-----------------|--------|
| 1 | — | `AgencyController::register()` | No defect. Registration is atomic (`DB::transaction`), creates user + agency + owner membership + starter subscription. Owner role assigned correctly. | N/A | N/A | `WorkspaceApprovalTest` (31 tests) | PASS |
| 2 | — | `AdminAgencyController::verify()/status()` | No defect. Requires `admin.manage_agencies`, writes audit log with from/to status. | N/A | N/A | `AdminAgencyOversightTest` | PASS |
| 3 | — | `AgencyStaffController::updateMember()` | Last-active-owner protection present (`activeOwnerCount() <= 1`). | N/A | N/A | `AdminStaffSubscriptionTest` | PASS |
| 4 | P1 | `AgencyStaffController::updateMember()` | **System-role escalation gap**: non-owner manager could assign a high-privilege system role (e.g. `accountant` with `finance.manage_payments`) to self or others. Custom-role check only covered `is_system === false`. | Delegation check skipped system roles. | Extended check to all roles (except owner role) using `Permissions::agencyRoleGrants($role->key)` for system roles. Owners may assign any role. | `test_manager_cannot_assign_role_with_permissions_beyond_delegation_authority`, `test_manager_cannot_elevate_self_via_role_assignment`, `test_owner_can_assign_any_role` | PASS |
| 5 | — | `AgencyStaffController::invite()` | Duplicate/expired invite checks run before plan-limit check (422 before 402) — correct ordering preserved from critical repairs. | N/A | N/A | `test_invitation_flow_adds_a_new_user_to_the_agency` | PASS |
| 6 | — | `SubscriptionController` | Plan catalog public; current subscription and invoices agency-scoped. | N/A | N/A | `test_plan_catalog_is_public_and_subscriptions_are_agency_scoped` | PASS |

## Persona verification

- **Owner**: full agency-wide operations including role/permission assignment — VERIFIED
- **Manager**: only delegated permissions; cannot grant beyond own grant set — VERIFIED (new tests)
- **Operator/Member**: no staff-management permission — enforced by `requirePermission('agency.manage_staff')`
- **Platform Admin**: `admin.manage_agencies` required for approval/suspension/transfer — VERIFIED

## Verdict

No unresolved P0/P1 defects in onboarding/lifecycle. One P1 escalation gap fixed with 3 regression tests.
