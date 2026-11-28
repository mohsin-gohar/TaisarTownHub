# Phase 2.15 — API Contract & Performance Audit Ledger

**Status: OPEN — inventory created; endpoint-by-endpoint contract verification is not yet complete.**  
**Branch:** `audit/phase-2.11-concurrency-fixes-20261128`  
**Source:** `backend/routes/api_definitions.php` and `docs/openapi.yaml`  
**Registration model:** the same route definitions are mounted under both `/api` and `/api/v1`.

## Scope and baseline

- Route declarations expand to **222 HTTP method + path operations** (the `Route::match` declarations count once per HTTP verb).
- OpenAPI currently defines **116 operations across 97 paths**.
- **106 route operations are not represented in OpenAPI**. This is a contract-documentation gap, not proof that the runtime endpoint itself is broken.
- Routes after the authenticated group declaration inherit Sanctum authentication and MFA verification; controller-level permission and agency-scope checks still need endpoint-specific verification.
- Do not mark a row complete until expected input, validation, response shape/statuses, permission/tenant isolation, and negative/error behavior have all been checked against the controller and tests.

## Confirmed findings / fixes in this phase

1. `GET /agencies` and `GET /agencies/{id}`: removed per-agency member/listing count queries by using `withCount` aggregate subqueries.
2. `GET /locations`: removed per-sector and per-category listing count queries by using `withCount` aggregate subqueries.
3. `AgencyPromotionService::expireDue()`: eager-loads each promotion's agency before the expiry loop, avoiding lazy-load query growth.
4. Workspace media-sale audit: fetches prior media sale states in one `whereIn` query rather than one lookup per sold row.
5. Workspace financial audit: uses a hash lookup for changed IDs instead of repeated linear `in_array` scans.
6. `GET /admin/agencies-oversight`: replaced three storage SUM queries per agency with three grouped queries for the page (documents, document versions, media).
7. Map bulk uploads are capped at 50 images and map reorder requests at 500 IDs to bound request CPU, memory and database writes.
8. Public directory query inputs now validate types and bounds for agency, dealer, project, video and map filters; legacy inquiry lists now use a bounded result limit and a SQL subquery for dealer property scope.
9. Admin oversight detail reuses a single set of storage aggregates rather than re-running the same totals.
10. Added query-count regression tests for agency directory, location index and admin storage oversight, plus request-size/query validation tests. These tests are committed but have not yet been executed.

## Known remaining performance risks

- `WorkspaceController::syncList()` still performs a per-row existence lookup and per-row upsert/ownership write for up to 2000 rows per collection. A bulk-upsert rewrite needs a separate concurrency/tenant-ownership design and regression tests before changing it.
- `AdminDashboardController` executes a fixed set of many aggregate queries per dashboard request. This is not N+1, but should be profiled and consolidated if response latency is high.
- `WorkspaceController::syncList()` still performs per-row read/upsert work for up to 2000 rows per collection; bulk write optimization remains open due tenant ownership/concurrency risk.
- `AgencyVideoController::index()` eager-loads comments for up to 200 videos in one relation query; this avoids N+1 but may produce a large response. Comment payload bounds/pagination need a compatibility decision.
- `MapController::publicIndex()` currently loads every active map; add a bounded/paginated contract if map count is expected to grow materially.
- No runtime query trace or production EXPLAIN/slow-query log was available during this repository-only review. The new query-count tests still require execution in the backend test environment.

## OpenAPI operations missing from the route inventory

- `POST /subscribers` → `WorkspaceController@subscribe` (Public)
- `GET /auth/google/redirect` → `AuthController@googleRedirect` (Public)
- `GET /auth/google/callback` → `AuthController@googleCallback` (Public)
- `POST /auth/google/exchange` → `AuthController@googleExchange` (Public)
- `POST /auth/email/verify` → `AuthController@verifyEmail` (Public)
- `POST /auth/mfa/verify` → `AuthController@mfaVerify` (Public)
- `POST /agency/register` → `AgencyController@register` (Public)
- `GET /agencies/featured` → `FeaturedAgencyController@index` (Public)
- `GET /videos` → `AgencyVideoController@index` (Public)
- `GET /videos/{id}` → `AgencyVideoController@show` (Public)
- `GET /maps` → `MapController@publicIndex` (Public)
- `GET /maps/{slug}` → `MapController@publicShow` (Public)
- `GET /media/{id}/file` → `MediaController@publicFile` (Public)
- `POST /payments/webhook/stripe` → `PaymentController@stripeWebhook` (Public)
- `POST /payments/webhook/flutterwave` → `PaymentController@flutterwaveWebhook` (Public)
- `GET /inquiries/captcha` → `InquiryController@captcha` (Public)
- `GET /auth/mfa/status` → `AuthController@mfaStatus` (Sanctum + MFA)
- `POST /auth/mfa/setup` → `AuthController@mfaSetup` (Sanctum + MFA)
- `POST /auth/mfa/enable` → `AuthController@mfaEnable` (Sanctum + MFA)
- `POST /auth/mfa/disable` → `AuthController@mfaDisable` (Sanctum + MFA)
- `GET /auth/login-history` → `AuthController@loginHistory` (Sanctum + MFA)
- `GET /workspace` → `WorkspaceController@show` (Sanctum + MFA)
- `PUT /workspace` → `WorkspaceController@save` (Sanctum + MFA)
- `GET /agency/profile` → `AgencyController@current` (Sanctum + MFA)
- `PUT /agency/profile` → `AgencyController@updateCurrent` (Sanctum + MFA)
- `PATCH /agency/profile` → `AgencyController@updateCurrent` (Sanctum + MFA)
- `PUT /agency/members/{id}` → `AgencyStaffController@updateMember` (Sanctum + MFA)
- `PATCH /agency/members/{id}` → `AgencyStaffController@updateMember` (Sanctum + MFA)
- `PUT /agency/roles/{id}` → `AgencyStaffController@updateRole` (Sanctum + MFA)
- `PATCH /agency/roles/{id}` → `AgencyStaffController@updateRole` (Sanctum + MFA)
- `PUT /properties/{id}` → `PropertyController@update` (Sanctum + MFA)
- `PATCH /properties/{id}` → `PropertyController@update` (Sanctum + MFA)
- `DELETE /properties/{id}` → `PropertyController@destroy` (Sanctum + MFA)
- `POST /properties/{id}/submit` → `PropertyController@submit` (Sanctum + MFA)
- `POST /properties/{id}/approve` → `PropertyController@approve` (Sanctum + MFA)
- `POST /properties/{id}/reject` → `PropertyController@reject` (Sanctum + MFA)
- `POST /properties/{id}/feature` → `PropertyController@feature` (Sanctum + MFA)
- `POST /properties/{id}/visibility` → `PropertyController@setMarketplaceVisibility` (Sanctum + MFA)
- `GET /moderation/queue` → `PropertyController@moderationQueue` (Sanctum + MFA)
- `PUT /dealers/{id}` → `DealerController@update` (Sanctum + MFA)
- `PATCH /dealers/{id}` → `DealerController@update` (Sanctum + MFA)
- `DELETE /dealers/{id}` → `DealerController@destroy` (Sanctum + MFA)
- `GET /dealer-files` → `DealerFilesController@index` (Sanctum + MFA)
- `POST /dealer-files` → `DealerFilesController@store` (Sanctum + MFA)
- `POST /dealer-files/{id}/sell` → `DealerFilesController@sell` (Sanctum + MFA)
- `GET /dealers/{dealerId}/sales-report` → `DealerFilesController@salesReport` (Sanctum + MFA)
- `GET /sales-report` → `DealerFilesController@allSalesReport` (Sanctum + MFA)
- `POST /videos` → `AgencyVideoController@store` (Sanctum + MFA)
- `POST /videos/{id}/like` → `AgencyVideoController@like` (Sanctum + MFA)
- `POST /videos/{id}/comments` → `AgencyVideoController@comment` (Sanctum + MFA)
- `PATCH /inquiries/{id}/status` → `InquiryController@updateStatus` (Sanctum + MFA)
- `PATCH /inquiries/{id}/notes` → `InquiryController@updateNotes` (Sanctum + MFA)
- `DELETE /inquiries/{id}` → `InquiryController@destroy` (Sanctum + MFA)
- `POST /agency/leads` → `LeadController@store` (Sanctum + MFA)
- `PUT /agency/leads/{id}` → `LeadController@update` (Sanctum + MFA)
- `PATCH /agency/leads/{id}` → `LeadController@update` (Sanctum + MFA)
- `PUT /agency/documents/{id}` → `DocumentController@update` (Sanctum + MFA)
- `PATCH /agency/documents/{id}` → `DocumentController@update` (Sanctum + MFA)
- `GET /agency/documents-activity` → `DocumentController@activity` (Sanctum + MFA)
- `GET /agency/document-folders` → `DocumentController@folders` (Sanctum + MFA)
- `POST /agency/document-folders` → `DocumentController@storeFolder` (Sanctum + MFA)
- `DELETE /agency/document-folders/{id}` → `DocumentController@destroyFolder` (Sanctum + MFA)
- `PUT /agency/media/{id}` → `MediaController@update` (Sanctum + MFA)
- `PATCH /agency/media/{id}` → `MediaController@update` (Sanctum + MFA)
- `POST /agency/media/{id}/sell` → `MediaController@sell` (Sanctum + MFA)
- `PATCH /agency/transactions/{id}` → `FinanceController@update` (Sanctum + MFA)
- `PATCH /agency/commissions/{id}` → `FinanceController@updateCommission` (Sanctum + MFA)
- `GET /transactions` → `TransactionController@index` (Sanctum + MFA)
- `POST /transactions` → `TransactionController@store` (Sanctum + MFA)
- `GET /saved-searches` → `SavedSearchController@index` (Sanctum + MFA)
- `POST /saved-searches` → `SavedSearchController@store` (Sanctum + MFA)
- `PUT /saved-searches/{id}` → `SavedSearchController@update` (Sanctum + MFA)
- `PATCH /saved-searches/{id}` → `SavedSearchController@update` (Sanctum + MFA)
- `DELETE /saved-searches/{id}` → `SavedSearchController@destroy` (Sanctum + MFA)
- `POST /locations/sectors` → `LocationController@storeSector` (Sanctum + MFA)
- `POST /locations/blocks` → `LocationController@storeBlock` (Sanctum + MFA)
- `POST /locations/categories` → `LocationController@storeCategory` (Sanctum + MFA)
- `POST /payments/subscription/checkout` → `PaymentController@subscriptionCheckout` (Sanctum + MFA)
- `GET /payments/flutterwave/verify/{tx_ref}` → `PaymentController@flutterwaveVerify` (Sanctum + MFA)
- `PATCH /admin/agencies/{id}/verify` → `AdminAgencyController@verify` (Sanctum + MFA)
- `PATCH /admin/agencies/{id}/status` → `AdminAgencyController@status` (Sanctum + MFA)
- `PATCH /admin/plans/{id}` → `AdminAgencyController@updatePlan` (Sanctum + MFA)
- `GET /admin/featured-agencies` → `AdminFeaturedAgencyController@index` (Sanctum + MFA)
- `POST /admin/featured-agencies` → `AdminFeaturedAgencyController@store` (Sanctum + MFA)
- `PUT /admin/featured-agencies/order` → `AdminFeaturedAgencyController@reorder` (Sanctum + MFA)
- `PUT /admin/featured-agencies/settings` → `AdminFeaturedAgencyController@updateSettings` (Sanctum + MFA)
- `POST /admin/featured-agencies/packages` → `AdminFeaturedAgencyController@storePackage` (Sanctum + MFA)
- `PUT /admin/featured-agencies/packages/{id}` → `AdminFeaturedAgencyController@updatePackage` (Sanctum + MFA)
- `PUT /admin/featured-agencies/{id}` → `AdminFeaturedAgencyController@update` (Sanctum + MFA)
- `PATCH /admin/featured-agencies/{id}` → `AdminFeaturedAgencyController@update` (Sanctum + MFA)
- `POST /admin/featured-agencies/{id}/cancel` → `AdminFeaturedAgencyController@cancel` (Sanctum + MFA)
- `GET /admin/maps` → `MapController@adminIndex` (Sanctum + MFA)
- `POST /admin/maps` → `MapController@store` (Sanctum + MFA)
- `POST /admin/maps/bulk` → `MapController@bulk` (Sanctum + MFA)
- `PUT /admin/maps/reorder` → `MapController@reorder` (Sanctum + MFA)
- `POST /admin/maps/{id}/replace-image` → `MapController@replaceImage` (Sanctum + MFA)
- `POST /admin/maps/{id}/toggle` → `MapController@toggle` (Sanctum + MFA)
- `POST /admin/maps/{id}/feature` → `MapController@feature` (Sanctum + MFA)
- `PUT /admin/maps/{id}` → `MapController@update` (Sanctum + MFA)
- `PATCH /admin/maps/{id}` → `MapController@update` (Sanctum + MFA)
- `DELETE /admin/maps/{id}` → `MapController@destroy` (Sanctum + MFA)
- `GET /admin/agencies-oversight` → `AdminAgencyOversightController@index` (Sanctum + MFA)
- `GET /admin/agencies-oversight/{id}` → `AdminAgencyOversightController@show` (Sanctum + MFA)
- `GET /admin/support-tickets/assignable-staff` → `SupportController@assignableStaff` (Sanctum + MFA)
- `PATCH /admin/users/{id}` → `AdminUserController@update` (Sanctum + MFA)
- `PATCH /admin/settings` → `AdminSystemController@updateSetting` (Sanctum + MFA)

## Complete route inventory

| Method | Path (under both `/api` and `/api/v1`) | Controller action | Route auth | OpenAPI |
|---|---|---|---|---|
| GET | `/catalog` | `WorkspaceController@catalog` | Public | Documented |
| POST | `/subscribers` | `WorkspaceController@subscribe` | Public | **Missing** |
| GET | `/health` | `HealthController@index` | Public | Documented |
| POST | `/auth/login` | `AuthController@login` | Public | Documented |
| POST | `/auth/register` | `AuthController@register` | Public | Documented |
| GET | `/auth/google/redirect` | `AuthController@googleRedirect` | Public | **Missing** |
| GET | `/auth/google/callback` | `AuthController@googleCallback` | Public | **Missing** |
| POST | `/auth/google/exchange` | `AuthController@googleExchange` | Public | **Missing** |
| POST | `/auth/forgot-password` | `AuthController@forgotPassword` | Public | Documented |
| POST | `/auth/reset-password` | `AuthController@resetPassword` | Public | Documented |
| GET | `/auth/email/verify` | `AuthController@verifyEmail` | Public | Documented |
| POST | `/auth/email/verify` | `AuthController@verifyEmail` | Public | **Missing** |
| POST | `/auth/mfa/verify` | `AuthController@mfaVerify` | Public | **Missing** |
| POST | `/agency/register` | `AgencyController@register` | Public | **Missing** |
| POST | `/agency/invitations/accept` | `AgencyStaffController@acceptInvitation` | Public | Documented |
| GET | `/agencies/featured` | `FeaturedAgencyController@index` | Public | **Missing** |
| GET | `/agencies` | `AgencyController@index` | Public | Documented |
| GET | `/agencies/{id}` | `AgencyController@show` | Public | Documented |
| GET | `/properties` | `PropertyController@index` | Public | Documented |
| GET | `/properties/{id}` | `PropertyController@show` | Public | Documented |
| GET | `/dealers` | `DealerController@index` | Public | Documented |
| GET | `/dealers/{id}` | `DealerController@show` | Public | Documented |
| GET | `/videos` | `AgencyVideoController@index` | Public | **Missing** |
| GET | `/videos/{id}` | `AgencyVideoController@show` | Public | **Missing** |
| GET | `/projects` | `ProjectController@index` | Public | Documented |
| GET | `/projects/{id}` | `ProjectController@show` | Public | Documented |
| GET | `/plans` | `SubscriptionController@plans` | Public | Documented |
| GET | `/locations` | `LocationController@index` | Public | Documented |
| GET | `/maps` | `MapController@publicIndex` | Public | **Missing** |
| GET | `/maps/{slug}` | `MapController@publicShow` | Public | **Missing** |
| GET | `/media/{id}/file` | `MediaController@publicFile` | Public | **Missing** |
| GET | `/shares/{token}/download` | `DocumentController@sharedDownload` | Public | Documented |
| POST | `/payments/webhook/stripe` | `PaymentController@stripeWebhook` | Public | **Missing** |
| POST | `/payments/webhook/flutterwave` | `PaymentController@flutterwaveWebhook` | Public | **Missing** |
| GET | `/inquiries/captcha` | `InquiryController@captcha` | Public | **Missing** |
| POST | `/inquiries` | `InquiryController@store` | Public | Documented |
| GET | `/auth/me` | `AuthController@me` | Sanctum + MFA | Documented |
| POST | `/auth/logout` | `AuthController@logout` | Sanctum + MFA | Documented |
| POST | `/auth/logout-all` | `AuthController@logoutAll` | Sanctum + MFA | Documented |
| POST | `/auth/email/resend` | `AuthController@resendEmailVerification` | Sanctum + MFA | Documented |
| GET | `/auth/mfa/status` | `AuthController@mfaStatus` | Sanctum + MFA | **Missing** |
| POST | `/auth/mfa/setup` | `AuthController@mfaSetup` | Sanctum + MFA | **Missing** |
| POST | `/auth/mfa/enable` | `AuthController@mfaEnable` | Sanctum + MFA | **Missing** |
| POST | `/auth/mfa/disable` | `AuthController@mfaDisable` | Sanctum + MFA | **Missing** |
| GET | `/auth/login-history` | `AuthController@loginHistory` | Sanctum + MFA | **Missing** |
| GET | `/workspace` | `WorkspaceController@show` | Sanctum + MFA | **Missing** |
| PUT | `/workspace` | `WorkspaceController@save` | Sanctum + MFA | **Missing** |
| GET | `/agency/profile` | `AgencyController@current` | Sanctum + MFA | **Missing** |
| PUT | `/agency/profile` | `AgencyController@updateCurrent` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/profile` | `AgencyController@updateCurrent` | Sanctum + MFA | **Missing** |
| GET | `/agency/members` | `AgencyStaffController@members` | Sanctum + MFA | Documented |
| POST | `/agency/members/invite` | `AgencyStaffController@invite` | Sanctum + MFA | Documented |
| PUT | `/agency/members/{id}` | `AgencyStaffController@updateMember` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/members/{id}` | `AgencyStaffController@updateMember` | Sanctum + MFA | **Missing** |
| DELETE | `/agency/members/{id}` | `AgencyStaffController@removeMember` | Sanctum + MFA | Documented |
| GET | `/agency/invitations` | `AgencyStaffController@invitations` | Sanctum + MFA | Documented |
| DELETE | `/agency/invitations/{id}` | `AgencyStaffController@revokeInvitation` | Sanctum + MFA | Documented |
| GET | `/agency/roles` | `AgencyStaffController@roles` | Sanctum + MFA | Documented |
| POST | `/agency/roles` | `AgencyStaffController@storeRole` | Sanctum + MFA | Documented |
| PUT | `/agency/roles/{id}` | `AgencyStaffController@updateRole` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/roles/{id}` | `AgencyStaffController@updateRole` | Sanctum + MFA | **Missing** |
| DELETE | `/agency/roles/{id}` | `AgencyStaffController@destroyRole` | Sanctum + MFA | Documented |
| POST | `/properties` | `PropertyController@store` | Sanctum + MFA | Documented |
| PUT | `/properties/{id}` | `PropertyController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/properties/{id}` | `PropertyController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/properties/{id}` | `PropertyController@destroy` | Sanctum + MFA | **Missing** |
| POST | `/properties/{id}/submit` | `PropertyController@submit` | Sanctum + MFA | **Missing** |
| POST | `/properties/{id}/approve` | `PropertyController@approve` | Sanctum + MFA | **Missing** |
| POST | `/properties/{id}/reject` | `PropertyController@reject` | Sanctum + MFA | **Missing** |
| POST | `/properties/{id}/feature` | `PropertyController@feature` | Sanctum + MFA | **Missing** |
| POST | `/properties/{id}/visibility` | `PropertyController@setMarketplaceVisibility` | Sanctum + MFA | **Missing** |
| GET | `/moderation/queue` | `PropertyController@moderationQueue` | Sanctum + MFA | **Missing** |
| POST | `/dealers` | `DealerController@store` | Sanctum + MFA | Documented |
| PUT | `/dealers/{id}` | `DealerController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/dealers/{id}` | `DealerController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/dealers/{id}` | `DealerController@destroy` | Sanctum + MFA | **Missing** |
| GET | `/dealer-files` | `DealerFilesController@index` | Sanctum + MFA | **Missing** |
| POST | `/dealer-files` | `DealerFilesController@store` | Sanctum + MFA | **Missing** |
| POST | `/dealer-files/{id}/sell` | `DealerFilesController@sell` | Sanctum + MFA | **Missing** |
| GET | `/dealers/{dealerId}/sales-report` | `DealerFilesController@salesReport` | Sanctum + MFA | **Missing** |
| GET | `/sales-report` | `DealerFilesController@allSalesReport` | Sanctum + MFA | **Missing** |
| POST | `/videos` | `AgencyVideoController@store` | Sanctum + MFA | **Missing** |
| POST | `/videos/{id}/like` | `AgencyVideoController@like` | Sanctum + MFA | **Missing** |
| POST | `/videos/{id}/comments` | `AgencyVideoController@comment` | Sanctum + MFA | **Missing** |
| GET | `/inquiries` | `InquiryController@index` | Sanctum + MFA | Documented |
| PATCH | `/inquiries/{id}/status` | `InquiryController@updateStatus` | Sanctum + MFA | **Missing** |
| PATCH | `/inquiries/{id}/notes` | `InquiryController@updateNotes` | Sanctum + MFA | **Missing** |
| DELETE | `/inquiries/{id}` | `InquiryController@destroy` | Sanctum + MFA | **Missing** |
| GET | `/agency/leads` | `LeadController@index` | Sanctum + MFA | Documented |
| GET | `/agency/leads/export` | `LeadController@export` | Sanctum + MFA | Documented |
| POST | `/agency/leads` | `LeadController@store` | Sanctum + MFA | **Missing** |
| GET | `/agency/leads/{id}` | `LeadController@show` | Sanctum + MFA | Documented |
| PUT | `/agency/leads/{id}` | `LeadController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/leads/{id}` | `LeadController@update` | Sanctum + MFA | **Missing** |
| POST | `/agency/leads/{id}/assign` | `LeadController@assign` | Sanctum + MFA | Documented |
| POST | `/agency/leads/{id}/activities` | `LeadController@storeActivity` | Sanctum + MFA | Documented |
| DELETE | `/agency/leads/{id}` | `LeadController@destroy` | Sanctum + MFA | Documented |
| GET | `/agency/documents` | `DocumentController@index` | Sanctum + MFA | Documented |
| POST | `/agency/documents` | `DocumentController@store` | Sanctum + MFA | Documented |
| PUT | `/agency/documents/{id}` | `DocumentController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/documents/{id}` | `DocumentController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/agency/documents/{id}` | `DocumentController@destroy` | Sanctum + MFA | Documented |
| POST | `/agency/documents/{id}/versions` | `DocumentController@storeVersion` | Sanctum + MFA | Documented |
| GET | `/agency/documents/{id}/versions` | `DocumentController@versions` | Sanctum + MFA | Documented |
| GET | `/agency/documents/{id}/download` | `DocumentController@download` | Sanctum + MFA | Documented |
| GET | `/agency/documents/{id}/preview` | `DocumentController@preview` | Sanctum + MFA | Documented |
| GET | `/agency/documents/{id}/shares` | `DocumentController@sharesIndex` | Sanctum + MFA | Documented |
| POST | `/agency/documents/{id}/shares` | `DocumentController@sharesStore` | Sanctum + MFA | Documented |
| DELETE | `/agency/documents/{id}/shares/{shareId}` | `DocumentController@sharesRevoke` | Sanctum + MFA | Documented |
| POST | `/agency/documents/{id}/restore` | `DocumentController@restore` | Sanctum + MFA | Documented |
| DELETE | `/agency/documents/{id}/permanent` | `DocumentController@forceDestroy` | Sanctum + MFA | Documented |
| GET | `/agency/documents/{id}/access-logs` | `DocumentController@accessLogs` | Sanctum + MFA | Documented |
| GET | `/agency/documents-quarantine` | `DocumentController@quarantineIndex` | Sanctum + MFA | Documented |
| POST | `/agency/documents/{id}/rescan` | `DocumentController@rescan` | Sanctum + MFA | Documented |
| GET | `/agency/documents/storage-usage` | `DocumentController@usage` | Sanctum + MFA | Documented |
| GET | `/agency/documents-activity` | `DocumentController@activity` | Sanctum + MFA | **Missing** |
| GET | `/agency/document-folders` | `DocumentController@folders` | Sanctum + MFA | **Missing** |
| POST | `/agency/document-folders` | `DocumentController@storeFolder` | Sanctum + MFA | **Missing** |
| DELETE | `/agency/document-folders/{id}` | `DocumentController@destroyFolder` | Sanctum + MFA | **Missing** |
| GET | `/agency/media` | `MediaController@index` | Sanctum + MFA | Documented |
| POST | `/agency/media` | `MediaController@store` | Sanctum + MFA | Documented |
| PUT | `/agency/media/{id}` | `MediaController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/agency/media/{id}` | `MediaController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/agency/media/{id}` | `MediaController@destroy` | Sanctum + MFA | Documented |
| POST | `/agency/media/{id}/sell` | `MediaController@sell` | Sanctum + MFA | **Missing** |
| GET | `/agency/media/{id}/file` | `MediaController@file` | Sanctum + MFA | Documented |
| GET | `/agency/transactions` | `FinanceController@index` | Sanctum + MFA | Documented |
| POST | `/agency/transactions` | `FinanceController@store` | Sanctum + MFA | Documented |
| GET | `/agency/transactions/{id}` | `FinanceController@show` | Sanctum + MFA | Documented |
| PUT | `/agency/transactions/{id}` | `FinanceController@update` | Sanctum + MFA | Documented |
| PATCH | `/agency/transactions/{id}` | `FinanceController@update` | Sanctum + MFA | **Missing** |
| POST | `/agency/transactions/{id}/void` | `FinanceController@void` | Sanctum + MFA | Documented |
| POST | `/agency/transactions/{id}/approve` | `FinanceController@approve` | Sanctum + MFA | Documented |
| GET | `/agency/transactions/{id}/payments` | `FinanceController@payments` | Sanctum + MFA | Documented |
| POST | `/agency/transactions/{id}/payments` | `FinanceController@storePayment` | Sanctum + MFA | Documented |
| POST | `/agency/transactions/{id}/schedules` | `FinanceController@storeSchedules` | Sanctum + MFA | Documented |
| POST | `/agency/transactions/{id}/commissions` | `FinanceController@storeCommission` | Sanctum + MFA | Documented |
| DELETE | `/agency/payments/{id}` | `FinanceController@destroyPayment` | Sanctum + MFA | Documented |
| GET | `/agency/commissions` | `FinanceController@commissions` | Sanctum + MFA | Documented |
| PUT | `/agency/commissions/{id}` | `FinanceController@updateCommission` | Sanctum + MFA | Documented |
| PATCH | `/agency/commissions/{id}` | `FinanceController@updateCommission` | Sanctum + MFA | **Missing** |
| GET | `/transactions` | `TransactionController@index` | Sanctum + MFA | **Missing** |
| POST | `/transactions` | `TransactionController@store` | Sanctum + MFA | **Missing** |
| GET | `/notifications` | `NotificationController@index` | Sanctum + MFA | Documented |
| GET | `/notifications/unread-count` | `NotificationController@unreadCount` | Sanctum + MFA | Documented |
| POST | `/notifications/read-all` | `NotificationController@markAllRead` | Sanctum + MFA | Documented |
| POST | `/notifications/{id}/read` | `NotificationController@markRead` | Sanctum + MFA | Documented |
| GET | `/favorites` | `FavoriteController@index` | Sanctum + MFA | Documented |
| POST | `/favorites/{propertyId}` | `FavoriteController@toggle` | Sanctum + MFA | Documented |
| DELETE | `/favorites/{propertyId}` | `FavoriteController@destroy` | Sanctum + MFA | Documented |
| GET | `/saved-searches` | `SavedSearchController@index` | Sanctum + MFA | **Missing** |
| POST | `/saved-searches` | `SavedSearchController@store` | Sanctum + MFA | **Missing** |
| PUT | `/saved-searches/{id}` | `SavedSearchController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/saved-searches/{id}` | `SavedSearchController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/saved-searches/{id}` | `SavedSearchController@destroy` | Sanctum + MFA | **Missing** |
| POST | `/locations/sectors` | `LocationController@storeSector` | Sanctum + MFA | **Missing** |
| POST | `/locations/blocks` | `LocationController@storeBlock` | Sanctum + MFA | **Missing** |
| POST | `/locations/categories` | `LocationController@storeCategory` | Sanctum + MFA | **Missing** |
| GET | `/agency/subscription` | `SubscriptionController@current` | Sanctum + MFA | Documented |
| GET | `/agency/subscription/invoices` | `SubscriptionController@invoices` | Sanctum + MFA | Documented |
| GET | `/agency/expenses` | `FinanceController@expensesIndex` | Sanctum + MFA | Documented |
| POST | `/agency/expenses` | `FinanceController@expensesStore` | Sanctum + MFA | Documented |
| DELETE | `/agency/expenses/{id}` | `FinanceController@expensesDestroy` | Sanctum + MFA | Documented |
| GET | `/agency/reports` | `FinanceController@reports` | Sanctum + MFA | Documented |
| GET | `/support/tickets` | `SupportController@index` | Sanctum + MFA | Documented |
| POST | `/support/tickets` | `SupportController@store` | Sanctum + MFA | Documented |
| GET | `/support/tickets/{id}` | `SupportController@show` | Sanctum + MFA | Documented |
| POST | `/support/tickets/{id}/messages` | `SupportController@storeMessage` | Sanctum + MFA | Documented |
| POST | `/support/tickets/{id}/status` | `SupportController@updateStatus` | Sanctum + MFA | Documented |
| POST | `/payments/subscription/checkout` | `PaymentController@subscriptionCheckout` | Sanctum + MFA | **Missing** |
| GET | `/payments/flutterwave/verify/{tx_ref}` | `PaymentController@flutterwaveVerify` | Sanctum + MFA | **Missing** |
| GET | `/admin/dashboard` | `AdminDashboardController@index` | Sanctum + MFA | Documented |
| GET | `/admin/reports` | `AdminDashboardController@reports` | Sanctum + MFA | Documented |
| GET | `/admin/agencies` | `AdminAgencyController@index` | Sanctum + MFA | Documented |
| GET | `/admin/agencies/{id}` | `AdminAgencyController@show` | Sanctum + MFA | Documented |
| PUT | `/admin/agencies/{id}/verify` | `AdminAgencyController@verify` | Sanctum + MFA | Documented |
| PATCH | `/admin/agencies/{id}/verify` | `AdminAgencyController@verify` | Sanctum + MFA | **Missing** |
| PUT | `/admin/agencies/{id}/status` | `AdminAgencyController@status` | Sanctum + MFA | Documented |
| PATCH | `/admin/agencies/{id}/status` | `AdminAgencyController@status` | Sanctum + MFA | **Missing** |
| GET | `/admin/plans` | `AdminAgencyController@plans` | Sanctum + MFA | Documented |
| POST | `/admin/plans` | `AdminAgencyController@storePlan` | Sanctum + MFA | Documented |
| PUT | `/admin/plans/{id}` | `AdminAgencyController@updatePlan` | Sanctum + MFA | Documented |
| PATCH | `/admin/plans/{id}` | `AdminAgencyController@updatePlan` | Sanctum + MFA | **Missing** |
| PUT | `/admin/agencies/{id}/subscription` | `AdminAgencyController@assignSubscription` | Sanctum + MFA | Documented |
| GET | `/admin/featured-agencies` | `AdminFeaturedAgencyController@index` | Sanctum + MFA | **Missing** |
| POST | `/admin/featured-agencies` | `AdminFeaturedAgencyController@store` | Sanctum + MFA | **Missing** |
| PUT | `/admin/featured-agencies/order` | `AdminFeaturedAgencyController@reorder` | Sanctum + MFA | **Missing** |
| PUT | `/admin/featured-agencies/settings` | `AdminFeaturedAgencyController@updateSettings` | Sanctum + MFA | **Missing** |
| POST | `/admin/featured-agencies/packages` | `AdminFeaturedAgencyController@storePackage` | Sanctum + MFA | **Missing** |
| PUT | `/admin/featured-agencies/packages/{id}` | `AdminFeaturedAgencyController@updatePackage` | Sanctum + MFA | **Missing** |
| PUT | `/admin/featured-agencies/{id}` | `AdminFeaturedAgencyController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/admin/featured-agencies/{id}` | `AdminFeaturedAgencyController@update` | Sanctum + MFA | **Missing** |
| POST | `/admin/featured-agencies/{id}/cancel` | `AdminFeaturedAgencyController@cancel` | Sanctum + MFA | **Missing** |
| GET | `/admin/maps` | `MapController@adminIndex` | Sanctum + MFA | **Missing** |
| POST | `/admin/maps` | `MapController@store` | Sanctum + MFA | **Missing** |
| POST | `/admin/maps/bulk` | `MapController@bulk` | Sanctum + MFA | **Missing** |
| PUT | `/admin/maps/reorder` | `MapController@reorder` | Sanctum + MFA | **Missing** |
| POST | `/admin/maps/{id}/replace-image` | `MapController@replaceImage` | Sanctum + MFA | **Missing** |
| POST | `/admin/maps/{id}/toggle` | `MapController@toggle` | Sanctum + MFA | **Missing** |
| POST | `/admin/maps/{id}/feature` | `MapController@feature` | Sanctum + MFA | **Missing** |
| PUT | `/admin/maps/{id}` | `MapController@update` | Sanctum + MFA | **Missing** |
| PATCH | `/admin/maps/{id}` | `MapController@update` | Sanctum + MFA | **Missing** |
| DELETE | `/admin/maps/{id}` | `MapController@destroy` | Sanctum + MFA | **Missing** |
| GET | `/admin/agencies-oversight` | `AdminAgencyOversightController@index` | Sanctum + MFA | **Missing** |
| GET | `/admin/agencies-oversight/{id}` | `AdminAgencyOversightController@show` | Sanctum + MFA | **Missing** |
| GET | `/admin/subscription-invoices` | `AdminAgencyController@invoicesIndex` | Sanctum + MFA | Documented |
| POST | `/admin/subscription-invoices` | `AdminAgencyController@invoicesStore` | Sanctum + MFA | Documented |
| POST | `/admin/subscription-invoices/{id}/verify` | `AdminAgencyController@invoiceVerify` | Sanctum + MFA | Documented |
| GET | `/admin/support-tickets` | `SupportController@adminIndex` | Sanctum + MFA | Documented |
| GET | `/admin/support-tickets/assignable-staff` | `SupportController@assignableStaff` | Sanctum + MFA | **Missing** |
| POST | `/admin/support-tickets/{id}/assign` | `SupportController@adminAssign` | Sanctum + MFA | Documented |
| GET | `/admin/users` | `AdminUserController@index` | Sanctum + MFA | Documented |
| POST | `/admin/users` | `AdminUserController@store` | Sanctum + MFA | Documented |
| PUT | `/admin/users/{id}` | `AdminUserController@update` | Sanctum + MFA | Documented |
| PATCH | `/admin/users/{id}` | `AdminUserController@update` | Sanctum + MFA | **Missing** |
| POST | `/admin/users/{id}/unlock` | `AdminUserController@unlock` | Sanctum + MFA | Documented |
| POST | `/admin/users/{id}/revoke-tokens` | `AdminUserController@revokeTokens` | Sanctum + MFA | Documented |
| GET | `/admin/roles` | `AdminUserController@roles` | Sanctum + MFA | Documented |
| GET | `/admin/audit-logs` | `AdminSystemController@auditLogs` | Sanctum + MFA | Documented |
| GET | `/admin/settings` | `AdminSystemController@settings` | Sanctum + MFA | Documented |
| PUT | `/admin/settings` | `AdminSystemController@updateSetting` | Sanctum + MFA | Documented |
| PATCH | `/admin/settings` | `AdminSystemController@updateSetting` | Sanctum + MFA | **Missing** |

## Verification checklist for every operation

- [ ] Request method/path and route registration (legacy + v1 parity)
- [ ] Required/optional query, path, JSON and multipart inputs
- [ ] Validation rules, bounds, enums, normalization and unknown-field behavior
- [ ] Response schema, status codes, pagination/array compatibility and sensitive-field allowlist
- [ ] Authentication, MFA, exact permission, platform-vs-agency scope and cross-tenant denial
- [ ] Not-found, forbidden, validation, throttling, conflict, plan-limit and server-error behavior
- [ ] Feature tests for success + invalid input + unauthorized/forbidden + not-found
- [ ] Query-count regression or bounded-query evidence for list endpoints
- [ ] OpenAPI operation and examples synchronized with the implementation

**No claim of full endpoint verification is made yet.** The 222-operation inventory is the tracking baseline for completing the controller groups systematically.
