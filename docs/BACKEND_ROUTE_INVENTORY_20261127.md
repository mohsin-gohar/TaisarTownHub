# Backend API Route Inventory — 2026-11-27

**Baseline:** `main` at `4baf396fb3074bac134dac9c8bdc40ba2da67bf0`  
**Source:** `backend/routes/api.php` and `backend/routes/api_definitions.php`

## Registration model
- `routes/api.php` includes the shared definitions once at `/api`, then again under `/api/v1`.
- The inventory below counts the **204 unique declarations** in the shared source once; runtime route registration is duplicated for legacy and versioned prefixes.
- The authenticated route group begins at source line 124; 168 declarations are inside that group and 35 are outside it (guest/public/webhook).
- Public and webhook routes use explicit per-route throttles where declared. The authenticated group adds Sanctum auth and `EnsureMfaVerified`; API middleware also resolves agency context globally.

## Static route-to-handler check
- Route declarations: 203
- Unique referenced controller classes: 31
- All 31 controller files were fetched and their PHP method declarations compared with every referenced action.
- Missing controller/action pairs in this static check: **0**.
- This is a static existence check, not proof that each route behaves correctly at runtime.

### HTTP declaration counts
- GET: 82
- POST: 77
- get: 1
- post: 1
- PUT: 6
- put: 18
- patch: 18
- DELETE: 17
- PATCH: 2

### Route declarations
| Source line | Method | Path (relative to prefix) | Handler | Access group |
|---:|---|---|---|---|
| 51 | GET | `/catalog` | `WorkspaceController@catalog` | Public/guest |
| 52 | POST | `/subscribers` | `WorkspaceController@subscribe` | Public/guest |
| 54 | GET | `/health` | `HealthController@index` | Public/guest |
| 61 | POST | `/auth/login` | `AuthController@login` | Public/guest |
| 62 | POST | `/auth/register` | `AuthController@register` | Public/guest |
| 63 | GET | `/auth/google/redirect` | `AuthController@googleRedirect` | Public/guest |
| 64 | GET | `/auth/google/callback` | `AuthController@googleCallback` | Public/guest |
| 65 | POST | `/auth/google/exchange` | `AuthController@googleExchange` | Public/guest |
| 66 | POST | `/auth/forgot-password` | `AuthController@forgotPassword` | Public/guest |
| 67 | POST | `/auth/reset-password` | `AuthController@resetPassword` | Public/guest |
| 68 | get|post | `/auth/email/verify` | `AuthController@verifyEmail` | Public/guest |
| 71 | POST | `/auth/mfa/verify` | `AuthController@mfaVerify` | Public/guest |
| 72 | POST | `/agency/register` | `AgencyController@register` | Public/guest |
| 73 | POST | `/agency/invitations/accept` | `AgencyStaffController@acceptInvitation` | Public/guest |
| 77 | GET | `/agencies/featured` | `FeaturedAgencyController@index` | Public/guest |
| 78 | GET | `/agencies` | `AgencyController@index` | Public/guest |
| 79 | GET | `/agencies/{id}` | `AgencyController@show` | Public/guest |
| 82 | GET | `properties` | `PropertyController@index` | Public/guest |
| 83 | GET | `properties/{id}` | `PropertyController@show` | Public/guest |
| 84 | GET | `dealers` | `DealerController@index` | Public/guest |
| 85 | GET | `dealers/{id}` | `DealerController@show` | Public/guest |
| 86 | GET | `videos` | `AgencyVideoController@index` | Public/guest |
| 87 | GET | `videos/{id}` | `AgencyVideoController@show` | Public/guest |
| 88 | GET | `projects` | `ProjectController@index` | Public/guest |
| 89 | GET | `projects/{id}` | `ProjectController@show` | Public/guest |
| 90 | GET | `/plans` | `SubscriptionController@plans` | Public/guest |
| 91 | GET | `/locations` | `LocationController@index` | Public/guest |
| 94 | GET | `/maps` | `MapController@publicIndex` | Public/guest |
| 95 | GET | `/maps/{slug}` | `MapController@publicShow` | Public/guest |
| 96 | GET | `/media/{id}/file` | `MediaController@publicFile` | Public/guest |
| 100 | GET | `/shares/{token}/download` | `DocumentController@sharedDownload` | Public/guest |
| 106 | POST | `/payments/webhook/stripe` | `PaymentController@stripeWebhook` | Public/guest |
| 107 | POST | `/payments/webhook/flutterwave` | `PaymentController@flutterwaveWebhook` | Public/guest |
| 113 | GET | `inquiries/captcha` | `InquiryController@captcha` | Public/guest |
| 114 | POST | `inquiries` | `InquiryController@store` | Public/guest |
| 126 | GET | `/auth/me` | `AuthController@me` | Authenticated |
| 127 | POST | `/auth/logout` | `AuthController@logout` | Authenticated |
| 128 | POST | `/auth/logout-all` | `AuthController@logoutAll` | Authenticated |
| 129 | POST | `/auth/email/resend` | `AuthController@resendEmailVerification` | Authenticated |
| 131 | GET | `/auth/mfa/status` | `AuthController@mfaStatus` | Authenticated |
| 132 | POST | `/auth/mfa/setup` | `AuthController@mfaSetup` | Authenticated |
| 133 | POST | `/auth/mfa/enable` | `AuthController@mfaEnable` | Authenticated |
| 134 | POST | `/auth/mfa/disable` | `AuthController@mfaDisable` | Authenticated |
| 135 | GET | `/auth/login-history` | `AuthController@loginHistory` | Authenticated |
| 138 | GET | `/workspace` | `WorkspaceController@show` | Authenticated |
| 139 | PUT | `/workspace` | `WorkspaceController@save` | Authenticated |
| 142 | GET | `/agency/profile` | `AgencyController@current` | Authenticated |
| 143 | put|patch | `/agency/profile` | `AgencyController@updateCurrent` | Authenticated |
| 146 | GET | `/agency/members` | `AgencyStaffController@members` | Authenticated |
| 147 | POST | `/agency/members/invite` | `AgencyStaffController@invite` | Authenticated |
| 148 | put|patch | `/agency/members/{id}` | `AgencyStaffController@updateMember` | Authenticated |
| 149 | DELETE | `/agency/members/{id}` | `AgencyStaffController@removeMember` | Authenticated |
| 150 | GET | `/agency/invitations` | `AgencyStaffController@invitations` | Authenticated |
| 151 | DELETE | `/agency/invitations/{id}` | `AgencyStaffController@revokeInvitation` | Authenticated |
| 152 | GET | `/agency/roles` | `AgencyStaffController@roles` | Authenticated |
| 153 | POST | `/agency/roles` | `AgencyStaffController@storeRole` | Authenticated |
| 154 | put|patch | `/agency/roles/{id}` | `AgencyStaffController@updateRole` | Authenticated |
| 155 | DELETE | `/agency/roles/{id}` | `AgencyStaffController@destroyRole` | Authenticated |
| 158 | POST | `properties` | `PropertyController@store` | Authenticated |
| 159 | put|patch | `properties/{id}` | `PropertyController@update` | Authenticated |
| 160 | DELETE | `properties/{id}` | `PropertyController@destroy` | Authenticated |
| 161 | POST | `properties/{id}/submit` | `PropertyController@submit` | Authenticated |
| 162 | POST | `properties/{id}/approve` | `PropertyController@approve` | Authenticated |
| 163 | POST | `properties/{id}/reject` | `PropertyController@reject` | Authenticated |
| 164 | POST | `properties/{id}/feature` | `PropertyController@feature` | Authenticated |
| 165 | POST | `properties/{id}/visibility` | `PropertyController@setMarketplaceVisibility` | Authenticated |
| 166 | GET | `/moderation/queue` | `PropertyController@moderationQueue` | Authenticated |
| 169 | POST | `dealers` | `DealerController@store` | Authenticated |
| 170 | put|patch | `dealers/{id}` | `DealerController@update` | Authenticated |
| 171 | DELETE | `dealers/{id}` | `DealerController@destroy` | Authenticated |
| 174 | GET | `dealer-files` | `DealerFilesController@index` | Authenticated |
| 175 | POST | `dealer-files` | `DealerFilesController@store` | Authenticated |
| 176 | POST | `dealer-files/{id}/sell` | `DealerFilesController@sell` | Authenticated |
| 177 | GET | `dealers/{dealerId}/sales-report` | `DealerFilesController@salesReport` | Authenticated |
| 178 | GET | `sales-report` | `DealerFilesController@allSalesReport` | Authenticated |
| 181 | POST | `videos` | `AgencyVideoController@store` | Authenticated |
| 182 | POST | `videos/{id}/like` | `AgencyVideoController@like` | Authenticated |
| 183 | POST | `videos/{id}/comments` | `AgencyVideoController@comment` | Authenticated |
| 186 | GET | `inquiries` | `InquiryController@index` | Authenticated |
| 187 | PATCH | `inquiries/{id}/status` | `InquiryController@updateStatus` | Authenticated |
| 188 | PATCH | `inquiries/{id}/notes` | `InquiryController@updateNotes` | Authenticated |
| 189 | DELETE | `inquiries/{id}` | `InquiryController@destroy` | Authenticated |
| 192 | GET | `/agency/leads` | `LeadController@index` | Authenticated |
| 193 | GET | `/agency/leads/export` | `LeadController@export` | Authenticated |
| 194 | POST | `/agency/leads` | `LeadController@store` | Authenticated |
| 195 | GET | `/agency/leads/{id}` | `LeadController@show` | Authenticated |
| 196 | put|patch | `/agency/leads/{id}` | `LeadController@update` | Authenticated |
| 197 | POST | `/agency/leads/{id}/assign` | `LeadController@assign` | Authenticated |
| 198 | POST | `/agency/leads/{id}/activities` | `LeadController@storeActivity` | Authenticated |
| 199 | DELETE | `/agency/leads/{id}` | `LeadController@destroy` | Authenticated |
| 202 | GET | `/agency/documents` | `DocumentController@index` | Authenticated |
| 205 | POST | `/agency/documents` | `DocumentController@store` | Authenticated |
| 206 | put|patch | `/agency/documents/{id}` | `DocumentController@update` | Authenticated |
| 207 | DELETE | `/agency/documents/{id}` | `DocumentController@destroy` | Authenticated |
| 208 | POST | `/agency/documents/{id}/versions` | `DocumentController@storeVersion` | Authenticated |
| 209 | GET | `/agency/documents/{id}/versions` | `DocumentController@versions` | Authenticated |
| 210 | GET | `/agency/documents/{id}/download` | `DocumentController@download` | Authenticated |
| 211 | GET | `/agency/documents/{id}/preview` | `DocumentController@preview` | Authenticated |
| 212 | GET | `/agency/documents/{id}/shares` | `DocumentController@sharesIndex` | Authenticated |
| 213 | POST | `/agency/documents/{id}/shares` | `DocumentController@sharesStore` | Authenticated |
| 214 | DELETE | `/agency/documents/{id}/shares/{shareId}` | `DocumentController@sharesRevoke` | Authenticated |
| 215 | POST | `/agency/documents/{id}/restore` | `DocumentController@restore` | Authenticated |
| 216 | DELETE | `/agency/documents/{id}/permanent` | `DocumentController@forceDestroy` | Authenticated |
| 217 | GET | `/agency/documents/{id}/access-logs` | `DocumentController@accessLogs` | Authenticated |
| 219 | GET | `/agency/documents-quarantine` | `DocumentController@quarantineIndex` | Authenticated |
| 220 | POST | `/agency/documents/{id}/rescan` | `DocumentController@rescan` | Authenticated |
| 221 | GET | `/agency/documents/storage-usage` | `DocumentController@usage` | Authenticated |
| 222 | GET | `/agency/documents-activity` | `DocumentController@activity` | Authenticated |
| 223 | GET | `/agency/document-folders` | `DocumentController@folders` | Authenticated |
| 224 | POST | `/agency/document-folders` | `DocumentController@storeFolder` | Authenticated |
| 225 | DELETE | `/agency/document-folders/{id}` | `DocumentController@destroyFolder` | Authenticated |
| 228 | GET | `/agency/media` | `MediaController@index` | Authenticated |
| 229 | POST | `/agency/media` | `MediaController@store` | Authenticated |
| 230 | put|patch | `/agency/media/{id}` | `MediaController@update` | Authenticated |
| 231 | DELETE | `/agency/media/{id}` | `MediaController@destroy` | Authenticated |
| 232 | POST | `/agency/media/{id}/sell` | `MediaController@sell` | Authenticated |
| 233 | GET | `/agency/media/{id}/file` | `MediaController@file` | Authenticated |
| 236 | GET | `/agency/transactions` | `FinanceController@index` | Authenticated |
| 237 | POST | `/agency/transactions` | `FinanceController@store` | Authenticated |
| 238 | GET | `/agency/transactions/{id}` | `FinanceController@show` | Authenticated |
| 239 | put|patch | `/agency/transactions/{id}` | `FinanceController@update` | Authenticated |
| 240 | POST | `/agency/transactions/{id}/void` | `FinanceController@void` | Authenticated |
| 241 | POST | `/agency/transactions/{id}/approve` | `FinanceController@approve` | Authenticated |
| 242 | GET | `/agency/transactions/{id}/payments` | `FinanceController@payments` | Authenticated |
| 243 | POST | `/agency/transactions/{id}/payments` | `FinanceController@storePayment` | Authenticated |
| 244 | POST | `/agency/transactions/{id}/schedules` | `FinanceController@storeSchedules` | Authenticated |
| 245 | POST | `/agency/transactions/{id}/commissions` | `FinanceController@storeCommission` | Authenticated |
| 246 | DELETE | `/agency/payments/{id}` | `FinanceController@destroyPayment` | Authenticated |
| 247 | GET | `/agency/commissions` | `FinanceController@commissions` | Authenticated |
| 248 | put|patch | `/agency/commissions/{id}` | `FinanceController@updateCommission` | Authenticated |
| 251 | GET | `transactions` | `TransactionController@index` | Authenticated |
| 252 | POST | `transactions` | `TransactionController@store` | Authenticated |
| 255 | GET | `/notifications` | `NotificationController@index` | Authenticated |
| 256 | GET | `/notifications/unread-count` | `NotificationController@unreadCount` | Authenticated |
| 257 | POST | `/notifications/read-all` | `NotificationController@markAllRead` | Authenticated |
| 258 | POST | `/notifications/{id}/read` | `NotificationController@markRead` | Authenticated |
| 261 | GET | `/favorites` | `FavoriteController@index` | Authenticated |
| 262 | POST | `/favorites/{propertyId}` | `FavoriteController@toggle` | Authenticated |
| 263 | DELETE | `/favorites/{propertyId}` | `FavoriteController@destroy` | Authenticated |
| 266 | GET | `/saved-searches` | `SavedSearchController@index` | Authenticated |
| 267 | POST | `/saved-searches` | `SavedSearchController@store` | Authenticated |
| 268 | put|patch | `/saved-searches/{id}` | `SavedSearchController@update` | Authenticated |
| 269 | DELETE | `/saved-searches/{id}` | `SavedSearchController@destroy` | Authenticated |
| 272 | POST | `/locations/sectors` | `LocationController@storeSector` | Authenticated |
| 273 | POST | `/locations/blocks` | `LocationController@storeBlock` | Authenticated |
| 274 | POST | `/locations/categories` | `LocationController@storeCategory` | Authenticated |
| 277 | GET | `/agency/subscription` | `SubscriptionController@current` | Authenticated |
| 278 | GET | `/agency/subscription/invoices` | `SubscriptionController@invoices` | Authenticated |
| 281 | GET | `/agency/expenses` | `FinanceController@expensesIndex` | Authenticated |
| 282 | POST | `/agency/expenses` | `FinanceController@expensesStore` | Authenticated |
| 283 | DELETE | `/agency/expenses/{id}` | `FinanceController@expensesDestroy` | Authenticated |
| 286 | GET | `/agency/reports` | `FinanceController@reports` | Authenticated |
| 289 | GET | `/support/tickets` | `SupportController@index` | Authenticated |
| 290 | POST | `/support/tickets` | `SupportController@store` | Authenticated |
| 291 | GET | `/support/tickets/{id}` | `SupportController@show` | Authenticated |
| 292 | POST | `/support/tickets/{id}/messages` | `SupportController@storeMessage` | Authenticated |
| 293 | POST | `/support/tickets/{id}/status` | `SupportController@updateStatus` | Authenticated |
| 297 | POST | `/payments/subscription/checkout` | `PaymentController@subscriptionCheckout` | Authenticated |
| 298 | GET | `/payments/flutterwave/verify/{tx_ref}` | `PaymentController@flutterwaveVerify` | Authenticated |
| 301 | GET | `/admin/dashboard` | `AdminDashboardController@index` | Authenticated |
| 302 | GET | `/admin/reports` | `AdminDashboardController@reports` | Authenticated |
| 305 | GET | `/admin/agencies` | `AdminAgencyController@index` | Authenticated |
| 306 | GET | `/admin/agencies/{id}` | `AdminAgencyController@show` | Authenticated |
| 307 | put|patch | `/admin/agencies/{id}/verify` | `AdminAgencyController@verify` | Authenticated |
| 308 | put|patch | `/admin/agencies/{id}/status` | `AdminAgencyController@status` | Authenticated |
| 309 | GET | `/admin/plans` | `AdminAgencyController@plans` | Authenticated |
| 310 | POST | `/admin/plans` | `AdminAgencyController@storePlan` | Authenticated |
| 311 | put|patch | `/admin/plans/{id}` | `AdminAgencyController@updatePlan` | Authenticated |
| 312 | PUT | `/admin/agencies/{id}/subscription` | `AdminAgencyController@assignSubscription` | Authenticated |
| 316 | GET | `/admin/featured-agencies` | `AdminFeaturedAgencyController@index` | Authenticated |
| 317 | POST | `/admin/featured-agencies` | `AdminFeaturedAgencyController@store` | Authenticated |
| 318 | PUT | `/admin/featured-agencies/order` | `AdminFeaturedAgencyController@reorder` | Authenticated |
| 319 | PUT | `/admin/featured-agencies/settings` | `AdminFeaturedAgencyController@updateSettings` | Authenticated |
| 320 | POST | `/admin/featured-agencies/packages` | `AdminFeaturedAgencyController@storePackage` | Authenticated |
| 321 | PUT | `/admin/featured-agencies/packages/{id}` | `AdminFeaturedAgencyController@updatePackage` | Authenticated |
| 322 | put|patch | `/admin/featured-agencies/{id}` | `AdminFeaturedAgencyController@update` | Authenticated |
| 323 | POST | `/admin/featured-agencies/{id}/cancel` | `AdminFeaturedAgencyController@cancel` | Authenticated |
| 326 | GET | `/admin/maps` | `MapController@adminIndex` | Authenticated |
| 327 | POST | `/admin/maps` | `MapController@store` | Authenticated |
| 328 | POST | `/admin/maps/bulk` | `MapController@bulk` | Authenticated |
| 329 | PUT | `/admin/maps/reorder` | `MapController@reorder` | Authenticated |
| 330 | POST | `/admin/maps/{id}/replace-image` | `MapController@replaceImage` | Authenticated |
| 331 | POST | `/admin/maps/{id}/toggle` | `MapController@toggle` | Authenticated |
| 332 | POST | `/admin/maps/{id}/feature` | `MapController@feature` | Authenticated |
| 333 | put|patch | `/admin/maps/{id}` | `MapController@update` | Authenticated |
| 334 | DELETE | `/admin/maps/{id}` | `MapController@destroy` | Authenticated |
| 337 | GET | `/admin/agencies-oversight` | `AdminAgencyOversightController@index` | Authenticated |
| 338 | GET | `/admin/agencies-oversight/{id}` | `AdminAgencyOversightController@show` | Authenticated |
| 341 | GET | `/admin/subscription-invoices` | `AdminAgencyController@invoicesIndex` | Authenticated |
| 342 | POST | `/admin/subscription-invoices` | `AdminAgencyController@invoicesStore` | Authenticated |
| 343 | POST | `/admin/subscription-invoices/{id}/verify` | `AdminAgencyController@invoiceVerify` | Authenticated |
| 346 | GET | `/admin/support-tickets` | `SupportController@adminIndex` | Authenticated |
| 347 | GET | `/admin/support-tickets/assignable-staff` | `SupportController@assignableStaff` | Authenticated |
| 348 | POST | `/admin/support-tickets/{id}/assign` | `SupportController@adminAssign` | Authenticated |
| 351 | GET | `/admin/users` | `AdminUserController@index` | Authenticated |
| 352 | POST | `/admin/users` | `AdminUserController@store` | Authenticated |
| 353 | put|patch | `/admin/users/{id}` | `AdminUserController@update` | Authenticated |
| 354 | POST | `/admin/users/{id}/unlock` | `AdminUserController@unlock` | Authenticated |
| 355 | POST | `/admin/users/{id}/revoke-tokens` | `AdminUserController@revokeTokens` | Authenticated |
| 356 | GET | `/admin/roles` | `AdminUserController@roles` | Authenticated |
| 359 | GET | `/admin/audit-logs` | `AdminSystemController@auditLogs` | Authenticated |
| 360 | GET | `/admin/settings` | `AdminSystemController@settings` | Authenticated |
| 361 | put|patch | `/admin/settings` | `AdminSystemController@updateSetting` | Authenticated |

## Review priorities
1. Verify route-level permission checks inside every handler; middleware presence alone is not authorization.
2. Compare the legacy `/api` and versioned `/api/v1` behavior and response contracts.
3. Review all admin, finance, document, upload, payment-webhook, and cross-agency routes for object-level access and auditability.
4. Confirm no unintentional public route exposes sensitive records; inspect health and signed-share endpoints separately.
