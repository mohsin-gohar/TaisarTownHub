# Phase 2.6 — Laravel Policies and Authorization Rules Review
Date: 2026-11-28
Repository: `mohsin-gohar/TaisarTownHub`
Branch reviewed: `audit/phase-1-file-inventory-20261128`
Reviewed HEAD: `8df11cf88388ce7568071dedde806a9d10f88e64`

## Scope and method
The current backend has no tracked `backend/app/Policies/` directory, no policy classes, no authorization provider, and no `Gate::` or controller `authorize()` calls found in repository code search. Authorization is implemented primarily through `AuthorizesApi`, `AgencyContext`, `User` permission resolution, the canonical `Permissions` catalog, `RolePermissions` legacy matrix, membership/role models, controller-level guards and route middleware.

Read the exact files listed below and cross-checked relevant role seeding, staff/role mutation endpoints, route declarations and existing authorization tests. This is a static review; no PHPUnit execution, database mutation test, HTTP runtime test or concurrent-request test was run.

## Exact files reviewed

### Core authorization and tenancy
- `backend/app/Http/Controllers/Api/AuthorizesApi.php`
- `backend/app/Http/Middleware/AgencyContext.php`
- `backend/app/Models/User.php`
- `backend/app/Models/AgencyMember.php`
- `backend/app/Models/Role.php`
- `backend/app/Support/Permissions.php`
- `backend/app/Support/RolePermissions.php`
- `backend/app/Support/LegacyTenancyResolver.php`
- `backend/bootstrap/app.php`
- `backend/routes/api_definitions.php`

### Role-management and permission initialization
- `backend/app/Http/Controllers/Api/AgencyStaffController.php`
- `backend/app/Http/Controllers/Api/AdminUserController.php`
- `backend/app/Http/Controllers/Api/LocationController.php`
- `backend/database/seeders/EnterpriseSeeder.php`

### Existing authorization tests inspected
- `backend/tests/Feature/AgencyContextTest.php`
- `backend/tests/Feature/AuthSecurityTest.php`
- `backend/tests/Feature/DealerFilesAuthorizationTest.php`
- `backend/tests/Feature/MfaEnforcementTest.php`
- `backend/tests/Feature/WorkspaceRoleGateTest.php`
- `backend/tests/Feature/WorkspaceTenantMatrixTest.php`
- `backend/tests/Unit/PermissionsCatalogTest.php`
- `backend/tests/Unit/RolePermissionsTest.php`

## Findings

### P1 — Agency staff managers can self-escalate to broad agency permissions
Files:
- `backend/app/Http/Controllers/Api/AgencyStaffController.php`, `updateMember()`, `storeRole()`, `updateRole()`
- `backend/app/Support/Permissions.php`, `agencyAssignable()`
- `backend/app/Models/AgencyMember.php`, `permissionKeys()`

A requester who has only `agency.manage_staff` can update their own membership's `permissions` array. The endpoint validates submitted keys only against `Permissions::agencyAssignable()`; it does not check whether the requester is authorized to delegate each permission or whether the target is the requester's own membership. The same staff manager can create a custom role containing any agency-assignable grants and then assign that role to themselves because `resolveAgencyRole()` accepts the agency's custom roles and the owner-only guard applies only when promoting to/from the fixed `role_agency_owner` role.

This enables a staff manager to grant themselves finance payment management, sensitive document access, staff administration, agency settings/subscription management, and other agency-level capabilities beyond their original role. Platform-only keys are correctly excluded, so this finding is an agency-scope privilege escalation rather than platform-admin escalation.

**Required remediation:** enforce a delegation ceiling. A requester must not grant permissions they do not possess or otherwise have explicit authority to delegate; prevent self-grant/self-role elevation unless performed by an authorized owner; protect role assignment and permission edits on owner memberships; consider separating staff administration from permission administration. Add feature tests for self-grant, custom-role self-assignment, escalation of another member, and legitimate owner delegation.

### P1 — MFA enforcement middleware is not active in the protected API pipeline
Files:
- `backend/app/Http/Middleware/EnsureMfaVerified.php`
- `backend/bootstrap/app.php`
- `backend/routes/api_definitions.php`

The previous Phase 2.5 review established that the middleware exists but is neither registered nor attached to the protected route groups. The current authorization model has an MFA verification state helper and tests for MFA policy/status, but those do not substitute for a middleware gate on protected requests. Treat MFA as not globally enforced until the runtime pipeline is wired and verified.

**Required remediation:** attach the middleware after Sanctum authentication to all routes that require a verified second factor, with explicit exemptions for login, registration and MFA challenge/verification endpoints. Add route-level tests proving unverified tokens are rejected and verified token IDs are accepted without authorizing another token.

### P2 — Authorization is distributed and policy rules are not centralized
Files:
- `AuthorizesApi.php`
- `AgencyContext.php`
- `User.php`
- `Permissions.php`
- `RolePermissions.php`
- Controllers and route definitions

No formal Laravel policies or Gate definitions are present. Permission decisions are split between middleware, a controller trait, model methods, hard-coded role rules and endpoint-specific controller checks. This makes it harder to prove every read/write action is consistently protected and increases the risk that a new endpoint omits a guard.

**Recommended remediation:** establish a single documented authorization contract and introduce policy classes (or an equally centralized authorization layer) incrementally, starting with high-risk property finance fields, documents, finance mutations, staff/role management and platform administration. Do not mass-rewrite all endpoints without characterization tests.

### P2 — AgencyContext protected-route detection depends on a literal middleware string
File: `backend/app/Http/Middleware/AgencyContext.php`, `handle()`.

The workspace approval gate determines whether a route is protected by searching `gatherMiddleware()` for the exact string `auth:sanctum`. A route later protected through a custom middleware group/alias or a different authentication guard may not be recognized as protected, causing the workspace approval check to be skipped. This was also identified in Phase 2.5.

**Recommended remediation:** use an explicit route metadata/middleware contract for workspace-protected routes or a robust authenticated-route classification; test both legacy and v1 routes and grouped middleware.

### P2 — Platform permission resolution relies on seeded platform role rows
Files:
- `backend/app/Models/User.php`, `platformPermissions()`
- `backend/database/seeders/EnterpriseSeeder.php`
- `backend/app/Support/Permissions.php`

The User resolver combines the legacy `RolePermissions::forRole()` matrix with permissions from a matching `roles.scope = platform` database row. The enterprise grants for platform roles (including `admin.manage_agencies`) are seeded by `EnterpriseSeeder`; they are not derived directly from `Permissions::PLATFORM_ROLE_GRANTS` at authorization time. Therefore a missing/stale platform role row or a deployment that has not run the required seeder can produce different effective platform access from the source catalog.

**Recommended remediation:** add a deterministic production-safe role/permission synchronization migration or deployment check, and fail closed with an explicit operational diagnostic when required system platform role rows are absent. Add tests for fresh install and stale/missing role-row behavior.

### P3 — Role and permission catalog compatibility has only partial invariant tests
Files:
- `backend/tests/Unit/PermissionsCatalogTest.php`
- `backend/tests/Unit/RolePermissionsTest.php`
- `backend/tests/Feature/AgencyContextTest.php`

Existing tests verify catalog key existence, exclusion of platform/legacy keys from agency assignability, selected legacy role restrictions, cross-agency context and several workspace collection permissions. They do not currently cover the self-escalation path in `AgencyStaffController::updateMember()`, broad custom-role delegation, or an endpoint-by-endpoint authorization matrix for every controller action.

**Recommended remediation:** build a data-driven role × permission × endpoint matrix and test both allowed and denied outcomes, including cross-agency context and owner/manager/member edge cases.

## Positive controls
- `AuthorizesApi::requirePermission()` returns 401 for unauthenticated calls and 403 when the permission check fails.
- Controller permission checks are agency-scoped by default; cross-agency platform grants are a separate explicit fallback.
- `AgencyContext` derives tenant context from current membership/access state rather than request-body agency IDs.
- `User::workspaceAccess()` checks account, membership and agency approval/status on each request.
- Platform-only catalog entries are excluded from `Permissions::agencyAssignable()`; custom role creation/update rejects those keys.
- Agency role resolution limits assignable roles to system agency roles or custom roles belonging to the current agency.
- Owner-role invitation/promotion/demotion protections exist; invitation acceptance claims the invite row under a transaction lock.
- Legacy admin matrix intentionally withholds selected high-risk legacy capabilities from `admin`; unit tests cover several of those exclusions.
- Location write actions call `requirePermission('properties.approve')` even though the route declarations do not add `auth:sanctum` explicitly; unauthenticated calls are rejected by the controller guard rather than being public writes.
- Workspace tenant-matrix tests cover a broad set of collection read/write/replace isolation cases.

## Verification and limitations
- Static source review completed for all listed authorization components and the inspected test files.
- No policy source files exist in the current tracked tree under `backend/app/Policies/`; no Gate or controller `authorize()` usage was found in repository code search.
- No source files changed in this review.
- No PHPUnit execution, role mutation exploit reproduction, database permission audit, route:list, HTTP authorization matrix, or MFA middleware integration test run.
- CI intentionally not triggered.
- The P1 self-escalation finding must be addressed before production authorization sign-off. **MFA wiring correction (Phase 2.7–2.9):** current `backend/routes/api_definitions.php` explicitly attaches `EnsureMfaVerified::class` to the authenticated API route group; the earlier statement that it was unattached was incorrect for reviewed snapshot `a3f0b5ad632ddf68562ba444bb1a5429a40711b0`. Runtime tests remain outstanding. See `PHASE_2_7_2_9_API_ROUTES_VALIDATION_RESPONSE_REVIEW_20261128.md`.
