<?php

/*
|--------------------------------------------------------------------------
| Shared API route definitions (registered twice by routes/api.php)
|--------------------------------------------------------------------------
| This file is required once at the bare `/api` prefix (legacy contract used
| by the current frontend) and once inside the `/api/v1` prefix (versioned
| contract for future/public consumers). Controllers are shared; route names
| under v1 are prefixed `v1.` so both registrations coexist safely.
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAgencyController;
use App\Http\Controllers\Api\AdminAgencyOversightController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminFeaturedAgencyController;
use App\Http\Controllers\Api\AdminSystemController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AgencyStaffController;
use App\Http\Controllers\Api\AgencyVideoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DealerController;
use App\Http\Controllers\Api\DealerFilesController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FeaturedAgencyController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WorkspaceController;

/*
|--------------------------------------------------------------------------
| Public / guest routes
|--------------------------------------------------------------------------
*/
Route::get('/catalog', [WorkspaceController::class, 'catalog'])->middleware('throttle:api.public');
Route::post('/subscribers', [WorkspaceController::class, 'subscribe'])->middleware('throttle:10,1');

Route::get('/health', [HealthController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authentication (Sanctum)
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api.auth');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:api.auth');
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])->middleware('throttle:api.auth');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->middleware('throttle:api.auth');
Route::post('/auth/google/exchange', [AuthController::class, 'googleExchange'])->middleware('throttle:api.auth');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:api.auth');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:api.auth');
Route::match(['get', 'post'], '/auth/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:api.auth');
// Phase 6 — second factor. Completes a pending sign-in (no token is issued
// before the code is verified); strictly rate limited.
Route::post('/auth/mfa/verify', [AuthController::class, 'mfaVerify'])->middleware('throttle:api.auth');
Route::post('/agency/register', [AgencyController::class, 'register'])->middleware('throttle:api.auth');
Route::post('/agency/invitations/accept', [AgencyStaffController::class, 'acceptInvitation'])->middleware('throttle:api.auth');

// Public directory of verified agencies. The featured roster must be
// registered before the `{id}` wildcard so it is never treated as an id.
Route::get('/agencies/featured', [FeaturedAgencyController::class, 'index'])->middleware('throttle:api.public');
Route::get('/agencies', [AgencyController::class, 'index'])->middleware('throttle:api.public');
Route::get('/agencies/{id}', [AgencyController::class, 'show'])->middleware('throttle:api.public');

// Public reads
Route::get('properties', [PropertyController::class, 'index'])->middleware('throttle:api.public');
Route::get('properties/{id}', [PropertyController::class, 'show'])->middleware('throttle:api.public');
Route::get('dealers', [DealerController::class, 'index'])->middleware('throttle:api.public');
Route::get('dealers/{id}', [DealerController::class, 'show'])->middleware('throttle:api.public');
Route::get('videos', [AgencyVideoController::class, 'index'])->middleware('throttle:api.public');
Route::get('videos/{id}', [AgencyVideoController::class, 'show'])->middleware('throttle:api.public');
Route::get('projects', [ProjectController::class, 'index'])->middleware('throttle:api.public');
Route::get('projects/{id}', [ProjectController::class, 'show'])->middleware('throttle:api.public');
Route::get('/plans', [SubscriptionController::class, 'plans'])->middleware('throttle:api.public');
Route::get('/locations', [LocationController::class, 'index'])->middleware('throttle:api.public');
Route::get('/media/{id}/file', [MediaController::class, 'publicFile'])->middleware('throttle:api.public');

// G-10: public share links — the URL token is a capability; hashed lookup,
// expiry/revocation/download-cap enforced server-side, no auth.
Route::get('/shares/{token}/download', [DocumentController::class, 'sharedDownload'])->middleware('throttle:30,1');

// Payment gateway webhooks MUST stay public: the gateways call them without a
// Sanctum token. Authenticity is enforced inside the controller (Stripe
// signature verification / Flutterwave verif-hash) and both are throttled.
// Registering them inside the auth group made every real delivery a 401.
Route::post('/payments/webhook/stripe', [PaymentController::class, 'stripeWebhook'])->middleware('throttle:60,1');
Route::post('/payments/webhook/flutterwave', [PaymentController::class, 'flutterwaveWebhook'])->middleware('throttle:60,1');

// Public lead capture (property-scoped leads get agency-attached server-side)
Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:api.write');

/*
|--------------------------------------------------------------------------
| Authenticated — every route below requires a Sanctum token.
| The AgencyContext middleware (appended in bootstrap/app.php) resolves the
| acting agency from the user's OWN memberships on each request.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureMfaVerified::class])->group(function () {

    /* ---------- Auth ---------- */
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:3,1');
    // Phase 6 — MFA enrolment, deactivation and sign-in history.
    Route::get('/auth/mfa/status', [AuthController::class, 'mfaStatus']);
    Route::post('/auth/mfa/setup', [AuthController::class, 'mfaSetup'])->middleware('throttle:5,1');
    Route::post('/auth/mfa/enable', [AuthController::class, 'mfaEnable'])->middleware('throttle:10,1');
    Route::post('/auth/mfa/disable', [AuthController::class, 'mfaDisable'])->middleware('throttle:10,1');
    Route::get('/auth/login-history', [AuthController::class, 'loginHistory']);

    /* ---------- Legacy workspace sync (hardened) ---------- */
    Route::get('/workspace', [WorkspaceController::class, 'show'])->middleware('auth:sanctum');
    Route::put('/workspace', [WorkspaceController::class, 'save'])->middleware('auth:sanctum');

    /* ---------- Agency profile ---------- */
    Route::get('/agency/profile', [AgencyController::class, 'current']);
    Route::match(['put', 'patch'], '/agency/profile', [AgencyController::class, 'updateCurrent']);

    /* ---------- Agency staff, invitations & roles (auth required) ---------- */
    Route::get('/agency/members', [AgencyStaffController::class, 'members'])->middleware('auth:sanctum');
    Route::post('/agency/members/invite', [AgencyStaffController::class, 'invite'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/agency/members/{id}', [AgencyStaffController::class, 'updateMember'])->middleware('auth:sanctum');
    Route::delete('/agency/members/{id}', [AgencyStaffController::class, 'removeMember'])->middleware('auth:sanctum');
    Route::get('/agency/invitations', [AgencyStaffController::class, 'invitations'])->middleware('auth:sanctum');
    Route::delete('/agency/invitations/{id}', [AgencyStaffController::class, 'revokeInvitation'])->middleware('auth:sanctum');
    Route::get('/agency/roles', [AgencyStaffController::class, 'roles'])->middleware('auth:sanctum');
    Route::post('/agency/roles', [AgencyStaffController::class, 'storeRole'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/agency/roles/{id}', [AgencyStaffController::class, 'updateRole'])->middleware('auth:sanctum');
    Route::delete('/agency/roles/{id}', [AgencyStaffController::class, 'destroyRole'])->middleware('auth:sanctum');

    /* ---------- Property lifecycle (auth required) ---------- */
    Route::post('properties', [PropertyController::class, 'store'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], 'properties/{id}', [PropertyController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('properties/{id}', [PropertyController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('properties/{id}/submit', [PropertyController::class, 'submit'])->middleware('auth:sanctum');
    Route::post('properties/{id}/approve', [PropertyController::class, 'approve'])->middleware('auth:sanctum');
    Route::post('properties/{id}/reject', [PropertyController::class, 'reject'])->middleware('auth:sanctum');
    Route::post('properties/{id}/feature', [PropertyController::class, 'feature'])->middleware('auth:sanctum');
    Route::post('properties/{id}/visibility', [PropertyController::class, 'setMarketplaceVisibility'])->middleware('auth:sanctum');
    Route::get('/moderation/queue', [PropertyController::class, 'moderationQueue'])->middleware('auth:sanctum');

    /* ---------- Dealers (legacy CRUD, auth required) ---------- */
    Route::post('dealers', [DealerController::class, 'store'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], 'dealers/{id}', [DealerController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('dealers/{id}', [DealerController::class, 'destroy'])->middleware('auth:sanctum');

    /* ---------- Dealer files & sales attribution (auth required) ---------- */
    Route::get('dealer-files', [DealerFilesController::class, 'index'])->middleware('auth:sanctum');
    Route::post('dealer-files', [DealerFilesController::class, 'store'])->middleware('auth:sanctum');
    Route::post('dealer-files/{id}/sell', [DealerFilesController::class, 'sell'])->middleware('auth:sanctum');
    Route::get('dealers/{dealerId}/sales-report', [DealerFilesController::class, 'salesReport'])->middleware('auth:sanctum');
    Route::get('sales-report', [DealerFilesController::class, 'allSalesReport'])->middleware('auth:sanctum');

    /* ---------- Video engagement (auth required — no impersonation) ---------- */
    Route::post('videos', [AgencyVideoController::class, 'store'])->middleware('auth:sanctum', 'throttle:10,1');
    Route::post('videos/{id}/like', [AgencyVideoController::class, 'like'])->middleware('auth:sanctum', 'throttle:30,1');
    Route::post('videos/{id}/comments', [AgencyVideoController::class, 'comment'])->middleware('auth:sanctum', 'throttle:10,1');

    /* ---------- Legacy inquiries (auth required) ---------- */
    Route::get('inquiries', [InquiryController::class, 'index'])->middleware('auth:sanctum');
    Route::patch('inquiries/{id}/status', [InquiryController::class, 'updateStatus'])->middleware('auth:sanctum');
    Route::patch('inquiries/{id}/notes', [InquiryController::class, 'updateNotes'])->middleware('auth:sanctum');
    Route::delete('inquiries/{id}', [InquiryController::class, 'destroy'])->middleware('auth:sanctum');

    /* ---------- CRM pipeline (agency leads, auth required) ---------- */
    Route::get('/agency/leads', [LeadController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/agency/leads/export', [LeadController::class, 'export'])->middleware('auth:sanctum');
    Route::post('/agency/leads', [LeadController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/agency/leads/{id}', [LeadController::class, 'show'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/agency/leads/{id}', [LeadController::class, 'update'])->middleware('auth:sanctum');
    Route::post('/agency/leads/{id}/assign', [LeadController::class, 'assign'])->middleware('auth:sanctum');
    Route::post('/agency/leads/{id}/activities', [LeadController::class, 'storeActivity'])->middleware('auth:sanctum');
    Route::delete('/agency/leads/{id}', [LeadController::class, 'destroy'])->middleware('auth:sanctum');

    /* ---------- Document vault (private disk streaming, auth required) ---------- */
    Route::get('/agency/documents', [DocumentController::class, 'index'])->middleware('auth:sanctum');
    // Upload endpoints get their own limiter so bulk-abusive clients cannot
    // ride the general 60/min API budget (per-file limits still apply).
    Route::post('/agency/documents', [DocumentController::class, 'store'])->middleware('auth:sanctum', 'throttle:20,1');
    Route::match(['put', 'patch'], '/agency/documents/{id}', [DocumentController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/agency/documents/{id}', [DocumentController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('/agency/documents/{id}/versions', [DocumentController::class, 'storeVersion'])->middleware('auth:sanctum', 'throttle:20,1');
    Route::get('/agency/documents/{id}/versions', [DocumentController::class, 'versions'])->middleware('auth:sanctum');
    Route::get('/agency/documents/{id}/download', [DocumentController::class, 'download'])->middleware('auth:sanctum');
    Route::get('/agency/documents/{id}/preview', [DocumentController::class, 'preview'])->middleware('auth:sanctum');
    Route::get('/agency/documents/{id}/shares', [DocumentController::class, 'sharesIndex'])->middleware('auth:sanctum');
    Route::post('/agency/documents/{id}/shares', [DocumentController::class, 'sharesStore'])->middleware('auth:sanctum');
    Route::delete('/agency/documents/{id}/shares/{shareId}', [DocumentController::class, 'sharesRevoke'])->middleware('auth:sanctum');
    Route::post('/agency/documents/{id}/restore', [DocumentController::class, 'restore'])->middleware('auth:sanctum');
    Route::delete('/agency/documents/{id}/permanent', [DocumentController::class, 'forceDestroy'])->middleware('auth:sanctum');
    Route::get('/agency/documents/{id}/access-logs', [DocumentController::class, 'accessLogs'])->middleware('auth:sanctum');
    // Scan lifecycle: quarantine visibility + rescan + storage usage.
    Route::get('/agency/documents-quarantine', [DocumentController::class, 'quarantineIndex'])->middleware('auth:sanctum');
    Route::post('/agency/documents/{id}/rescan', [DocumentController::class, 'rescan'])->middleware('auth:sanctum');
    Route::get('/agency/documents/storage-usage', [DocumentController::class, 'usage'])->middleware('auth:sanctum');
    Route::get('/agency/documents-activity', [DocumentController::class, 'activity'])->middleware('auth:sanctum');
    Route::get('/agency/document-folders', [DocumentController::class, 'folders'])->middleware('auth:sanctum');
    Route::post('/agency/document-folders', [DocumentController::class, 'storeFolder'])->middleware('auth:sanctum');
    Route::delete('/agency/document-folders/{id}', [DocumentController::class, 'destroyFolder'])->middleware('auth:sanctum');

    /* ---------- Media studio (auth required) ---------- */
    Route::get('/agency/media', [MediaController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/agency/media', [MediaController::class, 'store'])->middleware('auth:sanctum', 'throttle:20,1');
    Route::match(['put', 'patch'], '/agency/media/{id}', [MediaController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/agency/media/{id}', [MediaController::class, 'destroy'])->middleware('auth:sanctum');
    Route::post('/agency/media/{id}/sell', [MediaController::class, 'sell'])->middleware('auth:sanctum');
    Route::get('/agency/media/{id}/file', [MediaController::class, 'file'])->middleware('auth:sanctum');

    /* ---------- Finance: deals, payments, schedules, commissions (auth required) ---------- */
    Route::get('/agency/transactions', [FinanceController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/agency/transactions', [FinanceController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/agency/transactions/{id}', [FinanceController::class, 'show'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/agency/transactions/{id}', [FinanceController::class, 'update'])->middleware('auth:sanctum');
    Route::post('/agency/transactions/{id}/void', [FinanceController::class, 'void'])->middleware('auth:sanctum');
    Route::post('/agency/transactions/{id}/approve', [FinanceController::class, 'approve'])->middleware('auth:sanctum');
    Route::get('/agency/transactions/{id}/payments', [FinanceController::class, 'payments'])->middleware('auth:sanctum');
    Route::post('/agency/transactions/{id}/payments', [FinanceController::class, 'storePayment'])->middleware('auth:sanctum');
    Route::post('/agency/transactions/{id}/schedules', [FinanceController::class, 'storeSchedules'])->middleware('auth:sanctum');
    Route::post('/agency/transactions/{id}/commissions', [FinanceController::class, 'storeCommission'])->middleware('auth:sanctum');
    Route::delete('/agency/payments/{id}', [FinanceController::class, 'destroyPayment'])->middleware('auth:sanctum');
    Route::get('/agency/commissions', [FinanceController::class, 'commissions'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/agency/commissions/{id}', [FinanceController::class, 'updateCommission'])->middleware('auth:sanctum');

    /* ---------- Legacy transactions (auth required) ---------- */
    Route::get('transactions', [TransactionController::class, 'index'])->middleware('auth:sanctum');
    Route::post('transactions', [TransactionController::class, 'store'])->middleware('auth:sanctum');

    /* ---------- Notifications (auth required) ---------- */
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('auth:sanctum');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('auth:sanctum');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->middleware('auth:sanctum');

    /* ---------- Favorites (per-account, auth required) ---------- */
    Route::get('/favorites', [FavoriteController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/favorites/{propertyId}', [FavoriteController::class, 'toggle'])->middleware('auth:sanctum');
    Route::delete('/favorites/{propertyId}', [FavoriteController::class, 'destroy'])->middleware('auth:sanctum');

    /* ---------- Saved searches + email alerts (per-account) ---------- */
    Route::get('/saved-searches', [SavedSearchController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->middleware('auth:sanctum', 'throttle:30,1');
    Route::match(['put', 'patch'], '/saved-searches/{id}', [SavedSearchController::class, 'update'])->middleware('auth:sanctum', 'throttle:30,1');
    Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy'])->middleware('auth:sanctum');

    /* ---------- Locations & categories (reads public above) ---------- */
    Route::post('/locations/sectors', [LocationController::class, 'storeSector']);
    Route::post('/locations/blocks', [LocationController::class, 'storeBlock']);
    Route::post('/locations/categories', [LocationController::class, 'storeCategory']);

    /* ---------- Subscriptions (agency view, auth required) ---------- */
    Route::get('/agency/subscription', [SubscriptionController::class, 'current'])->middleware('auth:sanctum');
    Route::get('/agency/subscription/invoices', [SubscriptionController::class, 'invoices'])->middleware('auth:sanctum');

    /* ---------- Agency expenses (auth required) ---------- */
    Route::get('/agency/expenses', [FinanceController::class, 'expensesIndex'])->middleware('auth:sanctum');
    Route::post('/agency/expenses', [FinanceController::class, 'expensesStore'])->middleware('auth:sanctum');
    Route::delete('/agency/expenses/{id}', [FinanceController::class, 'expensesDestroy'])->middleware('auth:sanctum');

    /* ---------- Agency financial report (date-filtered, auth required) ---------- */
    Route::get('/agency/reports', [FinanceController::class, 'reports'])->middleware('auth:sanctum');

    /* ---------- Support desk (self-service, auth required) ---------- */
    Route::get('/support/tickets', [SupportController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/support/tickets', [SupportController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/support/tickets/{id}', [SupportController::class, 'show'])->middleware('auth:sanctum');
    Route::post('/support/tickets/{id}/messages', [SupportController::class, 'storeMessage'])->middleware('auth:sanctum');
    Route::post('/support/tickets/{id}/status', [SupportController::class, 'updateStatus'])->middleware('auth:sanctum');

    /* ---------- Payments (Stripe / Flutterwave) ---------- */
    // Webhooks are registered in the public section above (gateways have no token).
    Route::post('/payments/subscription/checkout', [PaymentController::class, 'subscriptionCheckout'])->middleware('auth:sanctum', 'throttle:10,1');
    Route::get('/payments/flutterwave/verify/{tx_ref}', [PaymentController::class, 'flutterwaveVerify'])->middleware('auth:sanctum', 'throttle:30,1');

    /* ---------- Admin: dashboard analytics (auth required) ---------- */
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/admin/reports', [AdminDashboardController::class, 'reports'])->middleware('auth:sanctum');

    /* ---------- Admin: agencies (auth required) ---------- */
    Route::get('/admin/agencies', [AdminAgencyController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/admin/agencies/{id}', [AdminAgencyController::class, 'show'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/agencies/{id}/verify', [AdminAgencyController::class, 'verify'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/agencies/{id}/status', [AdminAgencyController::class, 'status'])->middleware('auth:sanctum');
    Route::get('/admin/plans', [AdminAgencyController::class, 'plans'])->middleware('auth:sanctum');
    Route::post('/admin/plans', [AdminAgencyController::class, 'storePlan'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/plans/{id}', [AdminAgencyController::class, 'updatePlan'])->middleware('auth:sanctum');
    Route::put('/admin/agencies/{id}/subscription', [AdminAgencyController::class, 'assignSubscription'])->middleware('auth:sanctum');

    /* ---------- Admin: featured-agency placements (auth required) ---------- */
    // Static segments are registered before the `{id}` route.
    Route::get('/admin/featured-agencies', [AdminFeaturedAgencyController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/admin/featured-agencies', [AdminFeaturedAgencyController::class, 'store'])->middleware('auth:sanctum');
    Route::put('/admin/featured-agencies/order', [AdminFeaturedAgencyController::class, 'reorder'])->middleware('auth:sanctum');
    Route::put('/admin/featured-agencies/settings', [AdminFeaturedAgencyController::class, 'updateSettings'])->middleware('auth:sanctum');
    Route::post('/admin/featured-agencies/packages', [AdminFeaturedAgencyController::class, 'storePackage'])->middleware('auth:sanctum');
    Route::put('/admin/featured-agencies/packages/{id}', [AdminFeaturedAgencyController::class, 'updatePackage'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/featured-agencies/{id}', [AdminFeaturedAgencyController::class, 'update'])->middleware('auth:sanctum');
    Route::post('/admin/featured-agencies/{id}/cancel', [AdminFeaturedAgencyController::class, 'cancel'])->middleware('auth:sanctum');

    /* ---------- Admin: agency storage oversight (auth required) ---------- */
    Route::get('/admin/agencies-oversight', [AdminAgencyOversightController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/admin/agencies-oversight/{id}', [AdminAgencyOversightController::class, 'show'])->middleware('auth:sanctum');

    /* ---------- Admin: subscription invoices (auth required) ---------- */
    Route::get('/admin/subscription-invoices', [AdminAgencyController::class, 'invoicesIndex'])->middleware('auth:sanctum');
    Route::post('/admin/subscription-invoices', [AdminAgencyController::class, 'invoicesStore'])->middleware('auth:sanctum');
    Route::post('/admin/subscription-invoices/{id}/verify', [AdminAgencyController::class, 'invoiceVerify'])->middleware('auth:sanctum');

    /* ---------- Admin: support desk (auth required) ---------- */
    Route::get('/admin/support-tickets', [SupportController::class, 'adminIndex'])->middleware('auth:sanctum');
    Route::get('/admin/support-tickets/assignable-staff', [SupportController::class, 'assignableStaff'])->middleware('auth:sanctum');
    Route::post('/admin/support-tickets/{id}/assign', [SupportController::class, 'adminAssign'])->middleware('auth:sanctum');

    /* ---------- Admin: users & roles (auth required) ---------- */
    Route::get('/admin/users', [AdminUserController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/users/{id}', [AdminUserController::class, 'update'])->middleware('auth:sanctum');
    Route::post('/admin/users/{id}/unlock', [AdminUserController::class, 'unlock'])->middleware('auth:sanctum');
    Route::post('/admin/users/{id}/revoke-tokens', [AdminUserController::class, 'revokeTokens'])->middleware('auth:sanctum');
    Route::get('/admin/roles', [AdminUserController::class, 'roles'])->middleware('auth:sanctum');

    /* ---------- Admin: audit trail & system settings (auth required) ---------- */
    Route::get('/admin/audit-logs', [AdminSystemController::class, 'auditLogs'])->middleware('auth:sanctum');
    Route::get('/admin/settings', [AdminSystemController::class, 'settings'])->middleware('auth:sanctum');
    Route::match(['put', 'patch'], '/admin/settings', [AdminSystemController::class, 'updateSetting'])->middleware('auth:sanctum');
});
