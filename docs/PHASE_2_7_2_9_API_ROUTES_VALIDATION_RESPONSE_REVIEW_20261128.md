# Phase 2.7–2.9 — API Routes, Request Validation and Response Consistency Review

**Repository:** `mohsin-gohar/TaisarTownHub`  
**Branch:** `audit/phase-1-file-inventory-20261128`  
**Reviewed snapshot:** `a3f0b5ad632ddf68562ba444bb1a5429a40711b0`  
**Review date:** 2026-11-28  
**Review type:** Static source review; no source-code changes.

## Executive summary

- Reviewed all 203 route declarations in `backend/routes/api_definitions.php`, the dual-prefix registration in `backend/routes/api.php`, API bootstrap/exception handling, all 31 API endpoint controllers, the MFA middleware, and the API versioning/MFA feature tests.
- The shared route file is registered under both `/api` and `/api/v1`: 35 public declarations and 168 declarations inside the authenticated route group, yielding 406 registered route entries across both prefixes.
- The authenticated group explicitly includes both `auth:sanctum` and `EnsureMfaVerified`. A previous Phase 2.5/2.6 note saying this middleware was unattached is contradicted by the current reviewed source and must be treated as stale.
- No dedicated `FormRequest` classes or `Http/Resources` classes exist in the tracked backend tree. Validation and serialization are predominantly controller-owned.
- Main outstanding API concerns: exception status mapping in the generic API renderer; inconsistent response envelopes/raw model serialization; incomplete pagination/query-bound validation consistency; and a few endpoints with unusually broad or weakly bounded inputs already identified in the controller review.

## 2.7 — All API routes review

### Coverage and route inventory

Files reviewed:
- `backend/routes/api.php`
- `backend/routes/api_definitions.php`
- `backend/routes/web.php`
- `backend/bootstrap/app.php`
- `backend/app/Http/Middleware/EnsureMfaVerified.php`
- `backend/tests/Feature/ApiVersioningTest.php`
- `backend/tests/Feature/MfaEnforcementTest.php`

Route declarations in `api_definitions.php`:
- 203 declarations total.
- 35 public/guest declarations.
- 168 declarations inside the authenticated route group.
- HTTP declaration breakdown: GET 82, POST 77, MATCH 19, PUT 6, DELETE 17, PATCH 2.
- The shared definitions are required once at `/api` and once under `/api/v1`; expected route registrations therefore total 406.
- The 168 authenticated-group declarations inherit `auth:sanctum` and `EnsureMfaVerified` from the enclosing group. Ten declarations inside that group do not repeat per-route middleware; this is inherited protection, not a public-route gap.
- Three write routes for location sectors, blocks and categories also rely on the enclosing group for authentication, then perform controller-level permission checks.

### Positive controls

1. Public and authenticated route groups are visibly separated.
2. Authenticated routes are wrapped in `Route::middleware(['auth:sanctum', EnsureMfaVerified::class])`.
3. Login, registration, password reset, MFA verification, invitation acceptance and public lead capture have dedicated throttles.
4. Upload/document/media endpoints and sensitive write actions have additional route-level throttles.
5. Stripe and Flutterwave webhooks remain public as required for gateway callbacks and are throttled; signature/hash validation is performed in the payment controller.
6. The share-download endpoint is a capability-token route with a dedicated throttle; its controller enforces token expiry/revocation/download constraints.
7. Static routes such as featured agencies and map management are registered before parameterized wildcard routes where ordering matters.
8. `ApiVersioningTest` checks public legacy/v1 behavior, protected-route authentication, login/me roundtrip, route path parity and duplicate route names.

### Findings

- **P2 — Route declarations have no generated OpenAPI/route-contract artifact tied to the implementation.** The current source is the route contract; versioning parity is tested, but method-level parity is not explicitly asserted by `ApiVersioningTest` (the test reads methods and then discards them). Because both prefixes include the same PHP file, current parity is structurally guaranteed, but the test would not detect a future split-registration regression in HTTP verbs.
- **P3 — Public health endpoint has no route-specific throttle.** `GET /health` is intentionally simple and may be suitable for platform probes, but public exposure/rate policy should be an explicit operational choice.
- **P3 — API route comments are stale/inconsistent.** The route header says v1 names are prefixed, while `api.php` explicitly avoids route-name prefixing and the versioning test only checks uniqueness. No current functional break was established.

### MFA correction to earlier review

Current source proves `EnsureMfaVerified::class` is attached to the authenticated route group in `backend/routes/api_definitions.php`. `bootstrap/app.php` also appends `AgencyContext` to the API middleware stack. The middleware binds verification to the current Sanctum token and the feature test suite contains token-scoped MFA cases. Therefore, the previous Phase 2.5/2.6 statement that MFA middleware was not registered/attached is stale/incorrect for this reviewed snapshot. This is a source-level correction only; the test suite was not executed during this review.

## 2.8 — API request validation review

### Coverage

Reviewed request-handling and validation patterns across all 31 API endpoint controllers:
- `AdminAgencyController.php`
- `AdminAgencyOversightController.php`
- `AdminDashboardController.php`
- `AdminFeaturedAgencyController.php`
- `AdminSystemController.php`
- `AdminUserController.php`
- `AgencyController.php`
- `AgencyStaffController.php`
- `AgencyVideoController.php`
- `AuthController.php`
- `DealerController.php`
- `DealerFilesController.php`
- `DocumentController.php`
- `FavoriteController.php`
- `FeaturedAgencyController.php`
- `FinanceController.php`
- `HealthController.php`
- `InquiryController.php`
- `LeadController.php`
- `LocationController.php`
- `MapController.php`
- `MediaController.php`
- `NotificationController.php`
- `PaymentController.php`
- `ProjectController.php`
- `PropertyController.php`
- `SavedSearchController.php`
- `SubscriptionController.php`
- `SupportController.php`
- `TransactionController.php`
- `WorkspaceController.php`

No tracked `backend/app/Http/Requests/` directory or dedicated FormRequest classes were found. No tracked `backend/app/Http/Resources/` directory was found.

### Positive controls

1. Most create/update endpoints use Laravel `$request->validate()` before persistence.
2. Auth flows validate credentials, MFA codes, password reset inputs and enrollment/disable requests.
3. Document and media uploads validate file-related inputs and use dedicated upload throttles.
4. Finance endpoints validate monetary and transaction inputs at controller boundaries.
5. Public inquiry submission validates its payload and uses a signed single-use captcha challenge.
6. Map upload/management and agency role/member endpoints have explicit validation blocks.
7. Webhook authenticity is validated from gateway signatures/verif-hash rather than trusting client-supplied fields.

### Findings

- **P2 — Validation rules are distributed across controllers rather than reusable FormRequest classes.** This makes shared constraints, authorization-aware validation, and consistent normalization harder to audit and maintain. This is an architectural risk, not proof that every endpoint is unvalidated.
- **P2 — Query-string validation and pagination bounds are not uniform across list/report endpoints.** Controller review identified inconsistent pagination bounds and request-filter handling. Standardize page/per-page ceilings, sort allowlists, date ranges, filter types and maximum search lengths before database queries.
- **P2 — Existing high-impact input gaps remain from Phase 2.2 and should be treated as open until fixed/tested:** subscription checkout return URLs are syntax-validated but not constrained to an allowlist; map bulk upload has no explicit maximum image count/all-or-nothing cleanup; some public/workspace payload allowlists need review for unnecessary fields. See the Phase 2.2 controller report for exact endpoint-level findings.
- **P2 — Cross-field and business-rule validation is inconsistent.** Laravel field validation is present, but validation alone does not guarantee business invariants such as payment/refund ceilings, membership delegation authority, transition locking or tenant-consistent foreign keys. Those require transactional/domain checks and are tracked in earlier controller/model reviews.
- **P3 — No central API validation contract/schema is present.** A shared schema or OpenAPI contract would make request requirements and client expectations reviewable without reverse-engineering each controller.

## 2.9 — API response consistency verification

### Positive controls

1. API authentication failures are explicitly rendered as JSON with `success: false` and HTTP 401.
2. Validation failures are explicitly rendered as JSON with `success: false`, `message`, `errors` and HTTP 422.
3. Correlation ID middleware wraps API responses, including error responses.
4. MFA rejection uses a JSON response with HTTP 403 and the explicit `mfa_required` flag.
5. Several controllers consistently use success/message/resource envelopes for mutation responses.

### Findings

- **P1/P2 — Generic API exception rendering can misclassify common framework exceptions as HTTP 500.** In `backend/bootstrap/app.php`, the generic Throwable renderer derives status only from `getStatusCode()` and otherwise defaults to 500. Laravel `AuthorizationException` and `ModelNotFoundException` do not expose that method in their normal contract; unless converted earlier, forbidden/not-found conditions can become 500 responses. Add explicit renderers for authorization (403) and model-not-found (404), and test them. Severity: P1 if confirmed in runtime; static severity P2 pending HTTP reproduction.
- **P2 — Success response shapes are not uniform.** Some endpoints return raw Eloquent models/collections, some return `{success, resource}`, some return `{success, message}`, and others return paginator objects or bare arrays. Property, dealer, transaction and video endpoints demonstrate mixed serialization styles. This makes clients branch on endpoint-specific shapes and risks accidental field exposure when models evolve.
- **P2 — Public property serialization needs a consistent allowlist.** Phase 2.2 identified private finance fields and pending-change metadata exposed by the public property visibility response path. Public responses should use a dedicated resource/allowlist and tests asserting private attributes never appear.
- **P2 — Error envelopes differ by origin.** Authentication and validation errors have central envelopes, while MFA and controller-level business errors use endpoint-specific fields; some controller exception paths return raw exception messages. Standardize error codes, messages, validation errors and correlation IDs while preserving endpoint-specific metadata.
- **P3 — Pagination metadata and collection shape are not a documented shared contract.** List endpoints should consistently return `data`, `meta` (current page, per page, total, last page) and stable filter/sort metadata.
- **P3 — Serialization policy is implicit.** No API Resource classes are present; raw model serialization relies on model hidden/visible/casts and controller-specific transformations. This should be centralized for public, tenant and admin representations.

## Required remediation order

1. Fix and add regression tests for generic exception status mapping (403/404).
2. Add response allowlists/resources for public property, dealer, agency and other externally visible entities; close the Phase 2.2 public finance-field exposure.
3. Standardize API error and success envelopes and pagination metadata without breaking the existing frontend contract; version breaking changes under `/api/v1`.
4. Add bounded query validation (pagination, sort, filters, dates and search lengths) across all list/report endpoints.
5. Constrain payment return URLs to trusted application origins and add explicit limits for bulk map uploads.
6. Add route-level tests covering HTTP method, auth/MFA, permission, throttling and legacy/v1 parity.

## Verification limits

- Static source review only; no local Laravel runtime or database was available through this review workflow.
- `php artisan route:list`, PHPUnit, endpoint HTTP tests, fuzzing, OpenAPI generation and browser/client compatibility tests were not run.
- No source code or production configuration was changed.
- CI/workflows were intentionally not triggered.
- This report covers the API route definitions and controller request/response code, not a claim that every API behavior is runtime-verified.
