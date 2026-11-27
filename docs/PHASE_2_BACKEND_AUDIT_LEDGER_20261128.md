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


---

## Phase 2.2 — All Laravel controllers review

<badge>Reviewed — static</badge>

**Report:** [Phase 2.2 Laravel Controllers Review](PHASE_2_2_LARAVEL_CONTROLLERS_REVIEW_20261128.md)

**Reviewed snapshot:** `1504217879532fdfa7985cb41373e92b3c527f2d`  
**Coverage:** 33/33 tracked files under `backend/app/Http/Controllers/` (31 API controllers plus the shared authorization trait and base controller).

### Exact files reviewed

| Status | Current path |
|---|---|
| READ | `backend/app/Http/Controllers/Api/AdminAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminAgencyOversightController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminDashboardController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminSystemController.php` |
| READ | `backend/app/Http/Controllers/Api/AdminUserController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyStaffController.php` |
| READ | `backend/app/Http/Controllers/Api/AgencyVideoController.php` |
| READ | `backend/app/Http/Controllers/Api/AuthController.php` |
| READ | `backend/app/Http/Controllers/Api/AuthorizesApi.php` |
| READ | `backend/app/Http/Controllers/Api/DealerController.php` |
| READ | `backend/app/Http/Controllers/Api/DealerFilesController.php` |
| READ | `backend/app/Http/Controllers/Api/DocumentController.php` |
| READ | `backend/app/Http/Controllers/Api/FavoriteController.php` |
| READ | `backend/app/Http/Controllers/Api/FeaturedAgencyController.php` |
| READ | `backend/app/Http/Controllers/Api/FinanceController.php` |
| READ | `backend/app/Http/Controllers/Api/HealthController.php` |
| READ | `backend/app/Http/Controllers/Api/InquiryController.php` |
| READ | `backend/app/Http/Controllers/Api/LeadController.php` |
| READ | `backend/app/Http/Controllers/Api/LocationController.php` |
| READ | `backend/app/Http/Controllers/Api/MapController.php` |
| READ | `backend/app/Http/Controllers/Api/MediaController.php` |
| READ | `backend/app/Http/Controllers/Api/NotificationController.php` |
| READ | `backend/app/Http/Controllers/Api/PaymentController.php` |
| READ | `backend/app/Http/Controllers/Api/ProjectController.php` |
| READ | `backend/app/Http/Controllers/Api/PropertyController.php` |
| READ | `backend/app/Http/Controllers/Api/SavedSearchController.php` |
| READ | `backend/app/Http/Controllers/Api/SubscriptionController.php` |
| READ | `backend/app/Http/Controllers/Api/SupportController.php` |
| READ | `backend/app/Http/Controllers/Api/TransactionController.php` |
| READ | `backend/app/Http/Controllers/Api/WorkspaceController.php` |
| READ | `backend/app/Http/Controllers/Controller.php` |

### Main findings

1. **P1:** Property private financial fields are accepted without a separate finance permission gate; the visibility endpoint also returns private financial fields and `pending_changes` unconditionally.
2. **P1:** Finance refunds have no upper bound tied to the amount already collected, allowing an excessive refund to make ledger totals negative.
3. **P1:** Media sale mutation has no transaction/row lock, allowing concurrent sale requests to overwrite sale attribution.
4. **P2:** MFA challenge consumption uses a non-atomic cache read/verify/delete sequence.
5. **P2:** Stripe checkout return URLs are accepted from the client after URL syntax validation only.
6. **P2:** Last-active-agency-owner checks are not serialized with the membership mutation.
7. **P2:** Bulk map uploads lack a maximum image count and all-or-nothing file/database cleanup.
8. **P2:** Legacy public workspace property projection exposes `assignedDealerId`.
9. **P2:** Admin subscription invoice verification is not a locked state transition.
10. **P2:** Several upload flows need compensating cleanup if database persistence fails after bytes are written.
11. **P3:** Several paginated endpoints cap only the upper `per_page` bound and do not clamp the lower bound to 1.
12. **P3:** Finance, document, workspace and authentication controllers combine multiple business responsibilities and compatibility paths.

### Verification state

- Static controller source review: complete for all 33 controller-directory files.
- Findings recorded with file/method/line evidence in the Phase 2.2 report.
- No application controller source files changed.
- PHPUnit, runtime HTTP, concurrency and storage failure-injection tests: not run.
- CI: intentionally not triggered.
- Phase 2 backend audit remains in progress; this task completes controller review only.

## Phase 2 remaining

Continue with Phase 2.3 and later. Keep exact paths and evidence; do not infer READ/TESTED status from inventory, historical reports, or another file's references.
