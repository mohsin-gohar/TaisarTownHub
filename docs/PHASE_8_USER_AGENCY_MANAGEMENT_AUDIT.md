# Phase 8.2 — User & Agency Management Audit

Status: **VERIFIED**

## Files reviewed

- `backend/app/Http/Controllers/Api/AdminUserController.php` — all methods (`index`, `store`, `update`, `unlock`, `revokeTokens`, `roles`)
- `backend/app/Http/Controllers/Api/AdminAgencyController.php` — `verify()`, `status()`, plans, subscriptions, invoices
- `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php`
- `backend/routes/api_definitions.php` lines 305-312, 341-343, 351-356

## Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | All user-management endpoints require `admin.manage_users` | `AdminUserController.php:19,65,99,160,179`; roles require `admin.manage_roles` or `admin.manage_users` (:198) | VERIFIED |
| — | Agency verification/status require `admin.manage_agencies`, write audit log with from/to | `AdminAgencyController.php:136-168` | VERIFIED |
| — | Owner/member relationships enforced via `AgencyMember` + `activeOwnerCount()` last-owner protection | `AgencyStaffController.php` (Phase 7 repairs) | VERIFIED |
| — | Privileged changes produce audit records (`user.create`, `agency.status`, `agency.verify`) | controller `writeAudit()` calls | VERIFIED |
| — | Agency-scoped users cannot call admin user list | New authorization test | VERIFIED |
| P2 | Full-suite baseline has 1 FK-constraint failure in `AdminStaffSubscriptionTest::dashboard...` (synthetic `propertyId`) | Reproduced on baseline; PR #37 documented | EXTERNAL_PREEXISTING |

## Regression test

`Phase8SuperAdminAuthorizationTest`:
- `test_agency_owner_cannot_access_platform_only_surfaces` → 403/404 on `/api/admin/users`, `/api/admin/agencies`
- `test_agency_manager_cannot_approve_agency_or_moderate_listings` → 403 on verify/status/plan/setting
- `test_platform_admin_can_access_every_super_admin_surface` → 200 on all 11 endpoints

All **PASS**.

## Missing-record / duplicate / concurrent behavior

Not exhaustively fuzzed. Duplicate invitation prevention and last-owner protection verified via existing `AdminStaffSubscriptionTest` (18 passed in Phase 7). Concurrency partial: promotion locks verified in prior phases.
