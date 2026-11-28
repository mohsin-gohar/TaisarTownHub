# Phase 2 — Controller Audit

Scope: 31 controller classes in `app/Http/Controllers/Api` + base `Controller.php` + `AuthorizesApi` trait. Each public method inspected for request handling, validation, authorization, tenant scoping, transactions, exception handling, response consistency, pagination, performance, security.

## Defects repaired in Phase 2
1. **CRITICAL — cross-tenant dealer deletion (`DealerController::destroy`).** An agency manager with `manage_dealers` could soft-delete ANY agency's dealer by id (no tenancy check), while `update()` was correctly scoped. Fixed: only a platform user may delete across agencies; an agency member is confined to its own resolved agency (404 otherwise). Regression test added: `test_agency_scoped_publisher_cannot_delete_another_agencys_dealer`.
2. **MEDIUM — negative commission (`FinanceController::storeCommission`).** `amount` lacked `min:0`, allowing negative ledger entries. Fixed to `numeric|min:0|max:...`.
3. **MEDIUM — LIKE wildcard abuse (`DealerFilesController::index`).** (Documented, not yet patched) `strip_tags` does not escape `%`/`_`/`\`; a `%` search returns all rows. Recommend `escapeLike()`.

## Confirmed findings (evidence-backed)
| # | Controller/method | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | DealerController::destroy | DealerController.php:179 | CRITICAL | Cross-tenant delete — FIXED |
| 2 | FinanceController::storeCommission | FinanceController.php:968 | MEDIUM | Negative commission — FIXED |
| 3 | DealerFilesController::index | DealerFilesController.php:36 | MEDIUM | LIKE wildcard not escaped |
| 4 | ProjectController::index/show | ProjectController.php:14-44 | HIGH | No auth / no tenant scope (public projects) — verify intended |
| 5 | TransactionController::store | TransactionController.php:67 | HIGH | `create_transactions` permission not in RBAC catalogue — gate may be vacuous/blocking |
| 6 | DealerController::index/show | DealerController.php:18-73 | HIGH | Public dealer directory enumerates all agencies (no scope) — verify intended |
| 7 | FinanceController::storeSchedules | FinanceController.php:837-890 | MEDIUM | Loop of updateOrCreate (up to 120) without DB::transaction |
| 8 | InquiryController::updateStatus/updateNotes/destroy | InquiryController.php:226-315 | MEDIUM | Dual write Inquiry + BusinessRecord without transaction |
| 9 | MediaController::store | MediaController.php:100-266 | MEDIUM | File stored before DB row; orphan on DB failure |
| 10 | MapController::store/bulk | MapController.php:127,207 | MEDIUM | File stored before Map::create; orphan on failure |
| 11 | PropertyController::feature | PropertyController.php:825 | MEDIUM | `&&`/`||` precedence ambiguity in moderator check |
| 12 | Response shape | PropertyController.php:146-178,234,245,523; DealerController.php:51,62; ProjectController.php:33-43; AgencyVideoController.php:38-51; FinanceController.php:114-124 | MEDIUM | Inconsistent success/error envelopes; FinanceController duplicates rows under `transactions` and `data` |
| 13 | FavoriteController | FavoriteController.php:15-79 | MEDIUM | No explicit requirePermission (self-scoped by user_id, low risk) |
| 14 | FinanceController::destroyPayment | FinanceController.php:808 | LOW | Transaction lookup not agency-scoped (payment already proves tenancy) |
| 15 | AgencyController::current | AgencyController.php:304-311 | LOW | Six count() queries instead of withCount() |

## Well-secured controllers (no material findings)
AuthController, AgencyController (current/updateCurrent), AdminAgencyController, AdminDashboardController, AdminAgencyOversightController, AdminFeaturedAgencyController, AdminSystemController, AdminUserController, FeaturedAgencyController, HealthController, PropertyController (auth + row ownership + transactions + sensitive-field gating present), TransactionController (index/store scoped+locked), PaymentController (webhook signature + amount reconciliation + locked invoice updates), SubscriptionController, NotificationController, MediaController (content pipeline + malware gating + scoped delivery), MapController (MIME-derived ext + reorder in transaction), LocationController, LeadController, SupportController (ticket/message transactions + findVisible scoping), SavedSearchController, DocumentController (agency scoping, 404-not-403, malware lifecycle, locked share counter), DealerFilesController (sell locked), AgencyVideoController, AgencyStaffController (locked agency row for quota/owner checks).