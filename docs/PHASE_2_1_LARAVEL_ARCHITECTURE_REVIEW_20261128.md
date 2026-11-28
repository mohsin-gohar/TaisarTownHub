# Phase 2.1 — Laravel Application Architecture Review

**Review date:** 2026-11-28  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-1-file-inventory-20261128`  
**Reviewed HEAD before report:** `ce7216427479ef84e9044470849b8f16f2996b44`  
**Scope:** Laravel application bootstrap, route composition, middleware, authentication, tenancy/authorization boundaries, service registration, core configuration and test bootstrap.

## Executive result

The backend is a Laravel 12 / PHP 8.2+ API application using Sanctum bearer tokens, a Vite/React SPA frontend, MySQL as the configured primary database, database-backed queue/cache defaults, and explicit agency tenancy context. The framework bootstrap follows the Laravel 11/12 fluent configuration style. There is a recognizable separation between HTTP controllers, middleware, Eloquent models, support/policy helpers, services, providers, routes, and infrastructure configuration.

The architecture is **coherent but not yet cleanly layered**. Authorization and tenancy logic are concentrated in the User model and a controller trait; the API route definition file is registered twice to provide legacy and v1 prefixes; and API exception rendering has a likely HTTP status normalization defect that needs a focused regression test and correction.

This is a static architecture review of the listed files. No full application boot, route:list, dependency install, runtime request, or PHPUnit execution was performed in this task. CI was not triggered.

## 1. Architecture map

| Layer | Current implementation | Assessment |
|---|---|---|
| Entry points | `backend/public/index.php`, `backend/artisan` | Standard Laravel entry points |
| Bootstrap | `bootstrap/app.php`, `bootstrap/providers.php` | Laravel fluent bootstrap; middleware and exception policy centralized |
| Routing | `routes/web.php`, `routes/api.php`, `routes/api_definitions.php`, `routes/console.php` | API-first; shared definitions mounted under legacy and v1 prefixes |
| HTTP | API controllers, base Controller, `AuthorizesApi` trait | Controllers are grouped by business area; shared authorization/audit trait |
| Middleware | Correlation ID, trusted proxies, security headers, agency context, MFA gate | Cross-cutting concerns are explicit |
| Domain/data | Eloquent models; `User` owns workspace/permission resolution | Functional, but User model has excessive domain responsibility |
| Application services | Seven service classes in the current backend inventory | Some service extraction exists; not a consistent use-case/service layer |
| Authorization | `Permissions`, `RolePermissions`, role/membership relations, controller trait | Central catalog plus legacy compatibility matrix |
| Infrastructure | Config for DB, Redis, cache, queue, session, Sanctum, malware scanning | Environment-driven and container-compatible |
| Scheduling | `routes/console.php` | Pruning, saved-search alerts, quarantine cleanup scheduled |

## 2. Positive architecture controls

- Laravel 12 bootstrap uses explicit route registration and a centralized middleware pipeline.
- API requests receive correlation IDs, trusted-proxy-aware client IP handling, and security headers.
- Agency context is derived from current database memberships, not trusted from request payloads. Cross-agency context requires a platform permission.
- Protected API routes use `auth:sanctum` and `EnsureMfaVerified`; the MFA marker is bound to the current token rather than merely the user.
- User model hides password, lockout and MFA secret/recovery-code fields; password and MFA material use Laravel casts.
- Permission catalog separates platform-only permissions from agency-assignable permissions and retains legacy aliases for compatibility.
- The app service provider binds the malware scanner implementation through the container and defines named rate limiters.
- PHPUnit config explicitly uses a MySQL test database, array cache/session, sync queue, and disables external malware scanning for hermetic tests.
- Scheduled maintenance tasks use Laravel's scheduler and include overlap protection for saved-search alert processing.

## 3. Findings and remediation register

### P1 — API catch-all exception renderer can misclassify framework exceptions

**File:** `backend/bootstrap/app.php`

The catch-all API renderer derives the HTTP status only when the exception exposes `getStatusCode()`; otherwise it returns 500. Laravel authorization and model-not-found exceptions do not necessarily expose that Symfony method (they use Laravel exception status / not-found semantics). As written, exceptions that should produce 403 or 404 may be normalized to 500, obscuring client behavior and monitoring.

**Action:** Replace the narrow method check with Laravel's supported exception status/rendering semantics (or allow Laravel's default renderer to handle known HTTP/auth/model exceptions), and add feature tests for 401, 403, 404, 422, 429 and generic 500 JSON responses. Do not leak exception internals in production.

**Confidence:** High as a static control-flow concern; exact response mapping must be confirmed with a runtime regression test before marking fixed.

### P2 — User model combines persistence and multiple domain services

**File:** `backend/app/Models/User.php`

The User model currently owns account-state policy, MFA state, email normalization, password-reset notification routing, agency membership queries, tenant selection, workspace eligibility, platform-role detection, and effective permission resolution. This creates a high-coupling domain object and makes authorization changes difficult to test in isolation.

**Action:** In a later refactor, extract focused services/value objects such as `WorkspaceAccessResolver`, `AgencyContextResolver`, and `PermissionResolver`. Keep the model's relationships and small persistence-oriented helpers. Preserve behavior with characterization tests before moving logic.

**Risk:** Maintainability and regression risk, not proof of an exploitable vulnerability.

### P2 — Shared route file mounted twice; versioning contract is implicit

**Files:** `backend/routes/api.php`, `backend/routes/api_definitions.php`

The same large route-definition file is required once at the legacy prefix and again under `/v1`. This is a pragmatic compatibility bridge, but it doubles route registration and makes route names, middleware parity, and deprecation policy difficult to evolve independently. Comments in `api.php` and `api_definitions.php` disagree about whether v1 route names receive a `v1.` prefix; the actual group shown does not apply a name prefix.

**Action:** Define an explicit API versioning strategy: named route groups (if names are needed), shared controller registration helpers or separate version files, route parity tests, and a documented legacy sunset policy. Correct the misleading comments. Do not change endpoint contracts as part of this architecture-only review.

### P2 — Authorization logic is split across route middleware, middleware attributes, model methods and controller trait

**Files:** `bootstrap/app.php`, `AgencyContext.php`, `User.php`, `AuthorizesApi.php`, permission catalogs

The current design has meaningful safeguards, but the authorization decision depends on the ordering and cooperation of several layers. `AgencyContext` inspects the route's gathered middleware to decide whether workspace access should block a request, sets request attributes, and controller methods resolve permissions again. This is sensitive to route/middleware refactors.

**Action:** Document one canonical authorization flow and add integration tests that cover public routes with authenticated-but-unapproved users, protected routes, explicit agency headers, cross-agency platform users, inactive memberships, and missing membership. Consider moving the policy decision into a dedicated resolver while retaining middleware as an adapter.

### P2 — Default cache store is database despite Redis infrastructure being configured

**File:** `backend/config/cache.php`

The default cache store is `database`; Redis is available as a configured store and Compose infrastructure. Database cache may be acceptable for a small deployment, but it adds database load and can become a contention point for rate limiting, MFA markers, and other frequently accessed cache keys.

**Action:** Decide and document the intended production cache driver. If Redis is the supported production topology, set `CACHE_STORE=redis` in production environment configuration and verify Redis availability/failure behavior. Do not change local/test defaults without evidence.

### P3 — Redundant nested Sanctum middleware obscures route policy

**File:** `backend/routes/api_definitions.php`

Routes are already inside an `auth:sanctum` group, while many individual routes repeat `auth:sanctum`. This is functionally redundant in the current route layout and makes the route table harder to audit.

**Action:** Remove duplicates only after route:list and middleware-parity tests prove the effective stack is unchanged. This is cleanup, not a security fix.

### P3 — Queue dispatch transaction semantics need an explicit application-wide decision

**File:** `backend/config/queue.php`

Database and Redis queue connections set `after_commit=false`. If a future/current use-case dispatches a job inside a database transaction, the worker can race the transaction and observe missing or incomplete rows.

**Action:** Inventory all dispatch sites in the controller/service audit, then either enable after-commit behavior for relevant jobs/connections or use explicit after-commit dispatch. Add a transaction/queue integration test for affected workflows.

## 4. Configuration and deployment alignment

- PHP requirement is `^8.2`; Laravel framework is `^12.0`; Sanctum is `^4.0`.
- Auth config retains Laravel's `web` default guard, while API routes explicitly use `auth:sanctum`. This is valid for a token-based API, but tests and future routes must not assume the default guard is Sanctum.
- Sanctum accepts bearer tokens after configured guards; token expiration is 1,440 minutes. Token pruning is scheduled daily with a 48-hour pruning threshold.
- Primary DB default is MySQL; PHPUnit explicitly pins MySQL. SQLite should not be assumed to represent the production/test schema.
- Cache defaults to database; queue defaults to database; session defaults to database. Redis stores/connections are available.
- `APP_DEBUG` defaults false and timezone is UTC.
- `backend/README.md` is still the unmodified generic Laravel starter README and does not document the actual TaisarTownHub API architecture or local developer workflow.

## 5. Files read in this review

The exact 26 files are recorded in `docs/PHASE_2_BACKEND_AUDIT_LEDGER_20261128.md`. No old checklist box was marked complete: the historical ledger's paths and statuses are not a reliable current per-file audit tracker.

## 6. Verification limits and next work

- Static source review only; no runtime boot, route registration, test execution, performance profiling, or database query tracing.
- No application source/configuration changes were made in this task.
- CI was not triggered.
- Findings should be carried into later Phase 2 tasks: exception/error handling, service/domain boundaries, API versioning, authorization/tenant integration tests, cache/queue configuration, and developer documentation.
- The historical 706-file ledger remains a partial historical checklist; the current branch inventory is the path baseline. Review status must be tracked against exact current paths and evidence, not by the old 706 number.

## Conclusion

The Laravel application has a valid modern framework foundation and several deliberate security controls. The main architecture debt is concentrated in the oversized User domain model, tightly coupled tenancy/authorization flow, duplicated API route registration, and inconsistent exception status handling. The P1 exception-rendering concern is the first item to validate and fix in the subsequent error-handling/security pass.
