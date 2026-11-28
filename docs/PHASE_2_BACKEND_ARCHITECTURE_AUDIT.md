# Phase 2 — Laravel Architecture Audit

Branch: `audit/phase-2-backend-audit-20261128`
Base: Phase 1 deliverables (`0a5c6bd`) merged with latest `origin/main` (215 commits) at `9358f06`; parse-error repairs committed at `76faa76`.

## Application structure
- Laravel 11/12 backend under `backend/`, 34 controllers (31 in `Api/` + base + 2 traits), 46 Eloquent models, 7 service-layer files, 14 middleware, RBAC via `App\Support\RolePermissions` + `Permissions`, tenant context via `AgencyContext` middleware + `AuthorizesApi` trait.
- Routes centralized in `routes/api_definitions.php`, registered under both `/api` and `/api/v1` by `routes/api.php` (406 API routes).
- No `Policies/` directory and no `FormRequest` classes: authorization is centralized in `AuthorizesApi` (`requirePermission`/`requireAny`/`requireAgency`/`can`) and validation is inline `$request->validate()`.

## Service providers & container
- Standard `AppServiceProvider`, `AuthServiceProvider`, `EventServiceProvider`, `RouteServiceProvider` (or bootstrap/app.php equivalents). No custom container bindings of concern; services are resolved via constructor injection or static helpers.

## Dependency injection
- Controllers use constructor + method injection (`Request`). Services are a mix of instance (`AgencyPromotionService`) and static (`AgencyOnboarding`, `SecureFileStore`, `FileVaultCrypto`). Static helpers are acceptable but limit testability/mocking.

## Business-logic separation
- Controllers are generally thin and delegate to services for subscriptions, promotions, file vault, support tickets. Some controllers still hold multi-step logic (FinanceController schedule generation, InquiryController dual-write to legacy mirror) — see CONTROLLER and SERVICE audits.

## Error handling
- API exceptions are mostly caught and logged with `get_class($e)` only; generic client messages returned (no SQL/secret leakage). Validation throws `ValidationException` with field errors. Consistent use of `abort(4xx)` for auth/tenancy.

## Configuration management
- `config/` files for malware (fail-closed in production), sanctum, database. `.env.testing` pins MySQL `taisartownhub_test`, `MALWARE_SCAN_ENABLED=false`, `FILEVAULT_ENCRYPTION_ENABLED=false`.

## Defects found & repaired in Phase 2 (architecture-level)
1. **CRITICAL (repaired, commit 76faa76):** 7 double-backslash namespace parse errors (`\\App\\Models`, `\\Throwable`, `\\Illuminate\\...`) in `PropertyController`, `DocumentController`, `FinanceController`, `AuthSecurityTest`, `SupportTicketsTest` that made the entire backend fail to boot. Fixed to single-backslash FQCNs.
2. **Test isolation (diagnosed):** the default `taisartownhub_test` DB and `Storage::fake` temp dirs are shared across concurrent test processes; Phase 2 verification used a dedicated DB (`taisartownhub_p2`) and an isolated git worktree.

## Remaining architectural notes
- Static service helpers (`AgencyOnboarding`, `SecureFileStore`) are hard to mock; consider binding as singletons if deeper unit testing is required. Not a defect.
- No repository layer; Eloquent is used directly in controllers. Acceptable for this codebase size; not rewritten.