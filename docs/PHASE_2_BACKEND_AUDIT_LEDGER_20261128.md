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


---

## Phase 2.3 — All Laravel models and relationships review

<badge>Reviewed — static</badge>

**Report:** [Phase 2.3 Laravel Models and Relationships Review](PHASE_2_3_LARAVEL_MODELS_RELATIONSHIPS_REVIEW_20261128.md)

**Reviewed snapshot:** `55b48709b29e073c4fb645145db4831d14a65868`  
**Coverage:** 47/47 tracked files under `backend/app/Models/`; model relationship definitions were cross-checked against relevant migrations.

### Exact files reviewed

| Status | Current path |
|---|---|
| READ | `backend/app/Models/Agency.php` |
| READ | `backend/app/Models/AgencyInvitation.php` |
| READ | `backend/app/Models/AgencyMember.php` |
| READ | `backend/app/Models/AgencyPromotion.php` |
| READ | `backend/app/Models/AgencySubscription.php` |
| READ | `backend/app/Models/AgencyVideo.php` |
| READ | `backend/app/Models/AuditLog.php` |
| READ | `backend/app/Models/Block.php` |
| READ | `backend/app/Models/BusinessRecord.php` |
| READ | `backend/app/Models/Commission.php` |
| READ | `backend/app/Models/Dealer.php` |
| READ | `backend/app/Models/DealerFile.php` |
| READ | `backend/app/Models/Document.php` |
| READ | `backend/app/Models/DocumentAccessLog.php` |
| READ | `backend/app/Models/DocumentFolder.php` |
| READ | `backend/app/Models/DocumentQuarantine.php` |
| READ | `backend/app/Models/DocumentShare.php` |
| READ | `backend/app/Models/DocumentVersion.php` |
| READ | `backend/app/Models/EmailVerificationToken.php` |
| READ | `backend/app/Models/Expense.php` |
| READ | `backend/app/Models/Inquiry.php` |
| READ | `backend/app/Models/LeadActivity.php` |
| READ | `backend/app/Models/LoginHistory.php` |
| READ | `backend/app/Models/Map.php` |
| READ | `backend/app/Models/MediaFile.php` |
| READ | `backend/app/Models/PaymentSchedule.php` |
| READ | `backend/app/Models/Permission.php` |
| READ | `backend/app/Models/Project.php` |
| READ | `backend/app/Models/PromotionPackage.php` |
| READ | `backend/app/Models/Property.php` |
| READ | `backend/app/Models/PropertyCategory.php` |
| READ | `backend/app/Models/PropertyFavorite.php` |
| READ | `backend/app/Models/PropertyStatusHistory.php` |
| READ | `backend/app/Models/PropertyView.php` |
| READ | `backend/app/Models/Role.php` |
| READ | `backend/app/Models/SavedSearch.php` |
| READ | `backend/app/Models/Sector.php` |
| READ | `backend/app/Models/SubscriptionInvoice.php` |
| READ | `backend/app/Models/SubscriptionPlan.php` |
| READ | `backend/app/Models/SupportMessage.php` |
| READ | `backend/app/Models/SupportTicket.php` |
| READ | `backend/app/Models/SystemSetting.php` |
| READ | `backend/app/Models/Transaction.php` |
| READ | `backend/app/Models/TransactionPayment.php` |
| READ | `backend/app/Models/User.php` |
| READ | `backend/app/Models/VideoComment.php` |
| READ | `backend/app/Models/VideoLike.php` |

### Main findings

1. **P1:** `Role`, `Sector` and `Block` migrations have `deleted_at`, but their models do not use `SoftDeletes`; trashed rows remain in normal Eloquent queries/relations.
2. **P1:** Multiple tenant-owned child/parent relationships have independent foreign keys but no database-level same-agency guarantee; mismatched agency references must be audited and blocked in transactional services.
3. **P2:** Video comment/like tables lack foreign keys to their video/user parents, allowing orphaned engagement rows.
4. **P2:** Several models omit useful relations for existing foreign-key columns (for example, share/quarantine/access-log agency/document links and property-view/video-comment user links).
5. **P2:** Agency membership role assignment is not database-constrained to an agency-compatible role scope.
6. **P2:** `Project.subtitle` exists in the migration but is omitted from the model's fillable attributes; current ProjectController is read-only, so no active API write failure was confirmed.
7. **P3:** `AgencyPromotion::isPaid()` always returns false despite payment state fields; this is consistent with the current free-launch comment but must change before paid placements are enabled.
8. **P3:** Financial numeric casts/precision handling are inconsistent across models.

### Verification state

- Static model source review: complete for all 47 current model files.
- Relevant schema migrations cross-checked for FKs, unique constraints, indexes and soft-delete columns.
- No model or migration source files changed.
- No database migration execution, live schema inspection, orphan scan, tenant-mismatch scan, PHPUnit or static analysis run.
- CI: intentionally not triggered.
- Phase 2 backend audit remains in progress; this task completes model/relationship review only.


---

## Phase 2.4 — All Laravel services review

<badge>Reviewed — static</badge>

**Report:** [Phase 2.4 Laravel Services Review](PHASE_2_4_LARAVEL_SERVICES_REVIEW_20261128.md)

**Reviewed snapshot:** `d2a01d65dc4f5904b7ec9d2934bd30a5e05539dd`  
**Coverage:** 7/7 tracked PHP files under `backend/app/Services/`; service container, malware/vault configuration, relevant model scopes and integration points cross-checked.

### Exact files reviewed
- `backend/app/Services/AgencyOnboarding.php`
- `backend/app/Services/AgencyPromotionService.php`
- `backend/app/Services/ClamAVScanner.php`
- `backend/app/Services/DisabledMalwareScanner.php`
- `backend/app/Services/FileVaultCrypto.php`
- `backend/app/Services/MalwareScanner.php`
- `backend/app/Services/SecureFileStore.php`

### Main findings
1. **P1:** `SecureFileStore::move()` ignores destination write success and deletes the source regardless; store/put methods also ignore write results, risking file loss or DB records for missing bytes.
2. **P1:** ClamAV INSTREAM loop can break on `fread() === false`, still terminate the stream, and potentially accept an incomplete file as clean.
3. **P2:** Featured placement slot/agency uniqueness checks are not serialized; concurrent promotions can exceed capacity or duplicate placements.
4. **P2:** Vault upload, move, checksum, scan and response paths buffer complete files in PHP memory.
5. **P2:** Scanner availability reports a configured socket as available without checking reachability; CLI execution has no enforced process timeout.
6. **P2:** `scanStored()` does not verify the temporary plaintext file write before scanning.
7. **P3:** Vault envelope base64 fields are not strict-decoded or explicitly length-validated.

### Verification state
- Static review: 7/7 service files read.
- No service source files changed.
- No PHPUnit, PHP static analysis, storage failure-injection, ClamAV integration, database concurrency, or load test run.
- CI intentionally not triggered.

---

## Phase 2.5 — All Laravel middleware review

<badge>Reviewed — static</badge>

**Report:** [Phase 2.5 Laravel Middleware Review](PHASE_2_5_LARAVEL_MIDDLEWARE_REVIEW_20261128.md)

**Reviewed snapshot:** `d2a01d65dc4f5904b7ec9d2934bd30a5e05539dd`  
**Coverage:** 5/5 tracked PHP files under `backend/app/Http/Middleware/`; bootstrap, API route declarations and MFA verification helper cross-checked.

### Exact files reviewed
- `backend/app/Http/Middleware/AgencyContext.php`
- `backend/app/Http/Middleware/CorrelationId.php`
- `backend/app/Http/Middleware/EnsureMfaVerified.php`
- `backend/app/Http/Middleware/SecurityHeaders.php`
- `backend/app/Http/Middleware/TrustProxies.php`

### Main findings
1. **Superseded by Phase 2.7:** the current reviewed `api_definitions.php` explicitly attaches `EnsureMfaVerified` to the authenticated API group. The earlier inactive-middleware conclusion was incorrect for snapshot `a3f0b5a`; see the Phase 2.7–2.9 API review.
2. **P2:** `AgencyContext` detects protected routes by searching for the literal `auth:sanctum` middleware string, making the approval gate fragile if routes are reorganized under another auth alias/group.
3. **P2:** Trusted client IP/scheme handling depends on deployment-specific `TRUSTED_PROXIES` being set to the actual proxy CIDRs.
4. **P3:** The global restrictive CSP/sandbox requires browser verification for inline PDF/image previews and cross-origin public media.
5. **P3:** HSTS `includeSubDomains` is safe only if every covered subdomain supports HTTPS.

### Verification state
- Static review: 5/5 middleware files read.
- No middleware source files changed.
- No Laravel boot, route:list, HTTP/MFA integration, proxy spoofing, or browser media-preview test run.
- CI intentionally not triggered.


---

## Phase 2.6 — All policies and authorization rules review

<badge>Reviewed — static</badge>

**Report:** [Phase 2.6 Laravel Policies and Authorization Rules Review](PHASE_2_6_LARAVEL_AUTHORIZATION_REVIEW_20261128.md)

**Reviewed snapshot:** `8df11cf88388ce7568071dedde806a9d10f88e64`  
**Coverage:** Core authorization trait, agency context middleware, User permission resolution, membership/role models, canonical and legacy permission catalogs, tenancy resolver, bootstrap/routes, role seeding, staff/role administration controllers, and eight relevant authorization/security tests were inspected.

### Main findings
1. **P1:** An agency staff manager with `agency.manage_staff` can grant extra agency permissions to their own membership or create/assign a custom role to themselves; permission delegation is not bounded by the requester's own grants.
2. **Superseded by Phase 2.7:** `EnsureMfaVerified` is attached to the authenticated route group in the reviewed snapshot; runtime behavior still requires executing the existing MFA feature tests.
3. **P2:** No formal Laravel Policy classes or Gate definitions were found; authorization is distributed across middleware, controller trait, User model, role matrices and endpoint-specific checks.
4. **P2:** `AgencyContext` identifies protected routes through the literal `auth:sanctum` middleware string.
5. **P2:** Effective platform grants depend on platform role rows seeded by `EnterpriseSeeder`; missing/stale rows can make runtime grants diverge from the permission catalog.
6. **P3:** Existing unit/feature tests do not cover self-escalation, custom-role self-assignment or a complete endpoint authorization matrix.

### Positive controls
- Permission checks distinguish unauthenticated (401) from unauthorized (403) requests.
- Tenant context derives from current membership, not request-body agency IDs.
- Platform-only permissions are excluded from agency-assignable grants.
- Agency role resolution scopes custom roles to the acting agency.
- Owner-role promotion/invitation checks and invitation row locking are present.
- Existing tests cover key legacy role exclusions, cross-agency access and workspace tenant isolation.

### Verification state
- Static review only; no source authorization files changed.
- No PHPUnit execution, exploit reproduction, route:list, full endpoint authorization matrix, DB permission audit or MFA middleware integration test run.
- CI intentionally not triggered.
- P1 self-escalation requires remediation before production authorization sign-off. MFA middleware attachment is confirmed in source; runtime test execution remains outstanding.

## Phase 2 remaining

Continue with Phase 2.7 and subsequent backend areas. Reviews remain task-scoped; no claim that the entire backend is complete.


---

## Phase 2.7–2.9 — API routes, request validation and response consistency

**Report:** [Phase 2.7–2.9 API Routes, Validation and Response Review](PHASE_2_7_2_9_API_ROUTES_VALIDATION_RESPONSE_REVIEW_20261128.md)

**Reviewed snapshot:** `a3f0b5ad632ddf68562ba444bb1a5429a40711b0`  
**Coverage:** 203 route declarations across both API prefixes, API bootstrap/exception renderers, all 31 API endpoint controllers, MFA middleware, API versioning test and MFA feature tests.

### Main findings
1. **P2 (runtime confirmation required):** Generic API exception rendering only recognizes `getStatusCode()`; AuthorizationException and ModelNotFoundException may fall through to HTTP 500 instead of 403/404.
2. **P2:** API response shapes are inconsistent: raw models, bare collections, paginator payloads and success/resource envelopes coexist; no API Resource classes are present.
3. **P2:** Query-string validation and pagination bounds are inconsistent across list/report endpoints.
4. **P2:** Existing Phase 2.2 input/authorization issues remain open, including checkout return URL allowlisting and bulk map upload bounds.
5. **P3:** API versioning test checks route-path parity but discards HTTP method sets; health endpoint has no route-specific throttle.

### Important correction
The current source explicitly attaches `EnsureMfaVerified::class` alongside `auth:sanctum` to the authenticated route group. Earlier Phase 2.5 and 2.6 statements that this middleware was unattached are superseded/incorrect for the reviewed snapshot. Runtime test execution is still required.

### Verification state
- Static review only; no route:list, PHPUnit, HTTP contract tests, fuzzing or database-backed validation tests executed.
- No source-code changes.
- CI intentionally not triggered.
