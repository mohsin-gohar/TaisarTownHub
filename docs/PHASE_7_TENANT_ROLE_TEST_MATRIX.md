# Phase 7 — Tenant & Role Test Matrix

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Persona × Workflow matrix

| Workflow | Owner | Manager | Operator | Member | Platform Admin | Agency A → B denied |
|----------|-------|---------|----------|--------|----------------|---------------------|
| 7.1 Onboarding / staff | Full | Delegated only | No staff mgmt | Explicit grants | Platform-only | ✅ |
| 7.2 Property / media | Full | Delegated | Operational | View-granted | Moderation | ✅ |
| 7.3 CRM / leads | Full | Delegated | Assigned leads | Own leads | View-all | ✅ |
| 7.4 Document vault | Full | Delegated | Upload/view | Granted access | Oversight | ✅ |
| 7.5 Finance | Full | Delegated | No finance | finance.view only | Reports | ✅ |
| 7.6 Saved / notifications | Self | Self | Self | Self | Self | ✅ |

## New regression tests (this branch)

| Test | Prohibition proven |
|------|-------------------|
| `test_manager_cannot_assign_role_with_permissions_beyond_delegation_authority` | Manager cannot assign custom role containing `finance.manage_payments` / `crm.delete_lead` |
| `test_manager_cannot_elevate_self_via_role_assignment` | Manager cannot self-assign high-privilege role |
| `test_owner_can_assign_any_role` | Owner delegation preserved (negative control) |
| `test_agency_roles_cannot_grant_platform_permissions` (updated) | Manager cannot grant `crm.view` they don't possess |
| `test_members_and_roles_are_scoped_to_the_acting_agency` | Agency A roles invisible to Agency B |

## Backend enforcement (no frontend-only gates)

All tests call real API endpoints with Sanctum tokens against real MySQL rows. No assertions bypass policies or fabricate responses.

## Result

All persona/tenant negative tests pass. 469 backend tests, 0 failures.
