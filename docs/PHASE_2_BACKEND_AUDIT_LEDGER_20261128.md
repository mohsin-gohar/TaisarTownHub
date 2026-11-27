# Phase 2 — Laravel Backend Audit Ledger

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-1-file-inventory-20261128`  
**Baseline HEAD before Phase 2.1:** `ce7216427479ef84e9044470849b8f16f2996b44`  
**Status rule:** A file is marked reviewed only when its current-branch contents were fetched and inspected. Static review is not runtime/test evidence.

## Phase 2.1 — Laravel application architecture

<badge>Reviewed — static</badge>

**Report:** [Phase 2.1 Laravel Architecture Review](PHASE_2_1_LARAVEL_ARCHITECTURE_REVIEW_20261128.md)

### Exact files reviewed

| Status | Current path |
|---|---|
| READ | `backend/composer.json` |
| READ | `backend/bootstrap/app.php` |
| READ | `backend/bootstrap/providers.php` |
| READ | `backend/app/Providers/AppServiceProvider.php` |
| READ | `backend/routes/api.php` |
| READ | `backend/routes/api_definitions.php` |
| READ | `backend/routes/web.php` |
| READ | `backend/routes/console.php` |
| READ | `backend/app/Http/Controllers/Controller.php` |
| READ | `backend/app/Http/Controllers/Api/AuthorizesApi.php` |
| READ | `backend/app/Http/Middleware/AgencyContext.php` |
| READ | `backend/app/Http/Middleware/EnsureMfaVerified.php` |
| READ | `backend/app/Models/User.php` |
| READ | `backend/app/Support/Permissions.php` |
| READ | `backend/app/Support/RolePermissions.php` |
| READ | `backend/config/app.php` |
| READ | `backend/config/auth.php` |
| READ | `backend/config/database.php` |
| READ | `backend/config/cache.php` |
| READ | `backend/config/queue.php` |
| READ | `backend/config/sanctum.php` |
| READ | `backend/config/session.php` |
| READ | `backend/phpunit.xml` |
| READ | `backend/artisan` |
| READ | `backend/public/index.php` |
| READ | `backend/README.md` |

**Coverage:** 26 exact current paths inspected. No legacy ledger boxes were checked because the old ledger contains stale/nonexistent paths and unchecked historical tasks.

### Findings carried forward

1. **P1:** API catch-all exception renderer may turn Laravel authorization/not-found exceptions into HTTP 500; requires focused feature tests and correction.
2. **P2:** `User` model owns tenancy, workspace eligibility, role and permission resolution in addition to persistence.
3. **P2:** API v1 and legacy route sets are duplicated by requiring the same route file twice; versioning comments conflict on route-name prefix.
4. **P2:** Tenant authorization flow is spread across global API middleware, request attributes, model methods and controller trait.
5. **P2:** Database is the default cache store although Redis is configured; production choice should be explicit.
6. **P3:** Redundant `auth:sanctum` middleware is repeated inside an already authenticated route group.
7. **P3:** Queue `after_commit=false` needs dispatch-site inventory and transaction-race tests.
8. **P3:** Backend README remains generic Laravel starter documentation.

### Verification state

- Static source review: complete for the 26 paths above.
- Runtime route/bootstrap verification: not run.
- PHPUnit: not run in this task.
- Full backend source review: not complete; continue with Phase 2.2 onward.
- CI: intentionally not triggered.
- Application source/config changes: none in this task.

---

## Phase 2 remaining

Keep adding exact paths and evidence under each numbered Phase 2 task. Do not infer READ/TESTED status from file existence, inventory, historical reports, or another file's references.
