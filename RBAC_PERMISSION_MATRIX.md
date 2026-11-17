# RBAC Permission Matrix

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Role model (as implemented)

- `users.role` is the **legacy platform** key (`super_admin`, `admin`, `manager`, `moderator`, `support`, `operator`, `dealer`, `agent`, ...).
- `agency_members.role_id` is the **agency** role (`role_agency_owner`, `role_agency_manager`, `role_agent`, `role_viewer`, `role_accountant`, ...).
- Effective permissions: with an agency context, only that membership's role grants (+ per-member extras); without one, the legacy matrix ∪ seeded platform role row, and only for `isPlatformUser()`.
- `admin.manage_users` and `admin.manage_platform_settings` are excluded from the `admin` platform grant by design.

## Repairs affecting the matrix

1. Invitation acceptance writes `dealer` (never `admin`) to `users.role`; owner authority stays in `agency_members`. Owner invitations now require an active owner inviter. (`AdminStaffSubscriptionTest`, 3 new tests.)
2. `FinanceController::void` now requires an explicit void/update permission; a viewer with `finance.view` gets 403. (`test_void_requires_an_explicit_permission`.)
3. Dealer-file upload/sale/report now require `documents.upload`, `finance.create_transaction`, `documents.view` and `admin.view_reports` respectively. (`DealerFilesAuthorizationTest`, 7 tests.)
4. Legacy transaction list uses agency-aware `can()` instead of raw `hasPermission()` with no agency argument.

## Tested role coverage

Super Admin, Platform Admin, agency owner, manager, property manager, agent, document manager, accountant, viewer, operator and support are exercised across the pre-existing enterprise suite (full run: 239 tests). Custom agency roles cannot be granted platform permissions (`test_agency_roles_cannot_grant_platform_permissions`).

## Known inconsistencies (documented, not silently changed)

- Dealer deal visibility differs between legacy and finance APIs (policy decision required).
- `EnsureMfaVerified` global-admin policy still checks the legacy `users.role` whitelist instead of `isPlatformUser()`; the policy key is also unseeded and unsettable in the admin UI. Per-user MFA is enforced uniformly. Marked Medium, not fixed in this increment.
