<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAgencyController;
use App\Http\Controllers\Api\AdminDashboardController;
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
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WorkspaceController;

/*
|--------------------------------------------------------------------------
| Public / guest routes
|--------------------------------------------------------------------------
*/
Route::get('/catalog', [WorkspaceController::class, 'catalog']);
Route::post('/subscribers', [WorkspaceController::class, 'subscribe'])->middleware('throttle:10,1');

Route::get('/health', [HealthController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authentication (Sanctum)
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::post('/agency/register', [AgencyController::class, 'register'])->middleware('throttle:3,1');
Route::post('/agency/invitations/accept', [AgencyStaffController::class, 'acceptInvitation'])->middleware('throttle:10,1');

// Public directory of verified agencies
Route::get('/agencies', [AgencyController::class, 'index']);
Route::get('/agencies/{id}', [AgencyController::class, 'show']);

// Public reads
Route::get('properties', [PropertyController::class, 'index']);
Route::get('properties/{id}', [PropertyController::class, 'show']);
Route::get('dealers', [DealerController::class, 'index']);
Route::get('dealers/{id}', [DealerController::class, 'show']);
Route::get('videos', [AgencyVideoController::class, 'index']);
Route::get('videos/{id}', [AgencyVideoController::class, 'show']);
Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
Route::get('/plans', [SubscriptionController::class, 'plans']);
Route::get('/locations', [LocationController::class, 'index']);
Route::get('/media/{id}/file', [MediaController::class, 'publicFile']);

// Public lead capture (property-scoped leads get agency-attached server-side)
Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Authenticated — every route below requires a Sanctum token.
| The AgencyContext middleware (appended in bootstrap/app.php) resolves the
| acting agency from the user's OWN memberships on each request.
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /* ---------- Auth ---------- */
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

    /* ---------- Legacy workspace sync (hardened) ---------- */
    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::put('/workspace', [WorkspaceController::class, 'save']);

    /* ---------- Agency profile ---------- */
    Route::get('/agency/profile', [AgencyController::class, 'current']);
    Route::match(['put', 'patch'], '/agency/profile', [AgencyController::class, 'updateCurrent']);

    /* ---------- Agency staff, invitations & roles ---------- */
    Route::get('/agency/members', [AgencyStaffController::class, 'members']);
    Route::post('/agency/members/invite', [AgencyStaffController::class, 'invite']);
    Route::match(['put', 'patch'], '/agency/members/{id}', [AgencyStaffController::class, 'updateMember']);
    Route::delete('/agency/members/{id}', [AgencyStaffController::class, 'removeMember']);
    Route::get('/agency/invitations', [AgencyStaffController::class, 'invitations']);
    Route::delete('/agency/invitations/{id}', [AgencyStaffController::class, 'revokeInvitation']);
    Route::get('/agency/roles', [AgencyStaffController::class, 'roles']);
    Route::post('/agency/roles', [AgencyStaffController::class, 'storeRole']);
    Route::match(['put', 'patch'], '/agency/roles/{id}', [AgencyStaffController::class, 'updateRole']);
    Route::delete('/agency/roles/{id}', [AgencyStaffController::class, 'destroyRole']);

    /* ---------- Property lifecycle ---------- */
    Route::post('properties', [PropertyController::class, 'store']);
    Route::match(['put', 'patch'], 'properties/{id}', [PropertyController::class, 'update']);
    Route::delete('properties/{id}', [PropertyController::class, 'destroy']);
    Route::post('properties/{id}/submit', [PropertyController::class, 'submit']);
    Route::post('properties/{id}/approve', [PropertyController::class, 'approve']);
    Route::post('properties/{id}/reject', [PropertyController::class, 'reject']);
    Route::post('properties/{id}/feature', [PropertyController::class, 'feature']);
    Route::get('/moderation/queue', [PropertyController::class, 'moderationQueue']);

    /* ---------- Dealers (legacy CRUD) ---------- */
    Route::post('dealers', [DealerController::class, 'store']);
    Route::match(['put', 'patch'], 'dealers/{id}', [DealerController::class, 'update']);
    Route::delete('dealers/{id}', [DealerController::class, 'destroy']);

    /* ---------- Dealer files & sales attribution ---------- */
    Route::get('dealer-files', [DealerFilesController::class, 'index']);
    Route::post('dealer-files', [DealerFilesController::class, 'store']);
    Route::post('dealer-files/{id}/sell', [DealerFilesController::class, 'sell']);
    Route::get('dealers/{dealerId}/sales-report', [DealerFilesController::class, 'salesReport']);
    Route::get('sales-report', [DealerFilesController::class, 'allSalesReport']);

    /* ---------- Video engagement (auth required — no impersonation) ---------- */
    Route::post('videos', [AgencyVideoController::class, 'store'])->middleware('throttle:10,1');
    Route::post('videos/{id}/like', [AgencyVideoController::class, 'like'])->middleware('throttle:30,1');
    Route::post('videos/{id}/comments', [AgencyVideoController::class, 'comment'])->middleware('throttle:10,1');

    /* ---------- Legacy inquiries ---------- */
    Route::get('inquiries', [InquiryController::class, 'index']);
    Route::patch('inquiries/{id}/status', [InquiryController::class, 'updateStatus']);

    /* ---------- CRM pipeline (agency leads) ---------- */
    Route::get('/agency/leads', [LeadController::class, 'index']);
    Route::get('/agency/leads/export', [LeadController::class, 'export']);
    Route::post('/agency/leads', [LeadController::class, 'store']);
    Route::get('/agency/leads/{id}', [LeadController::class, 'show']);
    Route::match(['put', 'patch'], '/agency/leads/{id}', [LeadController::class, 'update']);
    Route::post('/agency/leads/{id}/assign', [LeadController::class, 'assign']);
    Route::post('/agency/leads/{id}/activities', [LeadController::class, 'storeActivity']);
    Route::delete('/agency/leads/{id}', [LeadController::class, 'destroy']);

    /* ---------- Document vault (private disk streaming) ---------- */
    Route::get('/agency/documents', [DocumentController::class, 'index']);
    Route::post('/agency/documents', [DocumentController::class, 'store']);
    Route::match(['put', 'patch'], '/agency/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/agency/documents/{id}', [DocumentController::class, 'destroy']);
    Route::post('/agency/documents/{id}/versions', [DocumentController::class, 'storeVersion']);
    Route::get('/agency/documents/{id}/versions', [DocumentController::class, 'versions']);
    Route::get('/agency/documents/{id}/download', [DocumentController::class, 'download']);
    Route::get('/agency/documents/{id}/preview', [DocumentController::class, 'preview']);
    Route::post('/agency/documents/{id}/restore', [DocumentController::class, 'restore']);
    Route::delete('/agency/documents/{id}/permanent', [DocumentController::class, 'forceDestroy']);
    Route::get('/agency/documents/{id}/access-logs', [DocumentController::class, 'accessLogs']);
    Route::get('/agency/documents-activity', [DocumentController::class, 'activity']);
    Route::get('/agency/document-folders', [DocumentController::class, 'folders']);
    Route::post('/agency/document-folders', [DocumentController::class, 'storeFolder']);
    Route::delete('/agency/document-folders/{id}', [DocumentController::class, 'destroyFolder']);

    /* ---------- Media studio ---------- */
    Route::get('/agency/media', [MediaController::class, 'index']);
    Route::post('/agency/media', [MediaController::class, 'store']);
    Route::match(['put', 'patch'], '/agency/media/{id}', [MediaController::class, 'update']);
    Route::delete('/agency/media/{id}', [MediaController::class, 'destroy']);
    Route::post('/agency/media/{id}/sell', [MediaController::class, 'sell']);
    Route::get('/agency/media/{id}/file', [MediaController::class, 'file']);

    /* ---------- Finance: deals, payments, schedules, commissions ---------- */
    Route::get('/agency/transactions', [FinanceController::class, 'index']);
    Route::post('/agency/transactions', [FinanceController::class, 'store']);
    Route::get('/agency/transactions/{id}', [FinanceController::class, 'show']);
    Route::match(['put', 'patch'], '/agency/transactions/{id}', [FinanceController::class, 'update']);
    Route::post('/agency/transactions/{id}/void', [FinanceController::class, 'void']);
    Route::post('/agency/transactions/{id}/approve', [FinanceController::class, 'approve']);
    Route::get('/agency/transactions/{id}/payments', [FinanceController::class, 'payments']);
    Route::post('/agency/transactions/{id}/payments', [FinanceController::class, 'storePayment']);
    Route::post('/agency/transactions/{id}/schedules', [FinanceController::class, 'storeSchedules']);
    Route::post('/agency/transactions/{id}/commissions', [FinanceController::class, 'storeCommission']);
    Route::delete('/agency/payments/{id}', [FinanceController::class, 'destroyPayment']);
    Route::get('/agency/commissions', [FinanceController::class, 'commissions']);
    Route::match(['put', 'patch'], '/agency/commissions/{id}', [FinanceController::class, 'updateCommission']);

    /* ---------- Legacy transactions ---------- */
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);

    /* ---------- Notifications ---------- */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    /* ---------- Favorites (per-account) ---------- */
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{propertyId}', [FavoriteController::class, 'toggle']);
    Route::delete('/favorites/{propertyId}', [FavoriteController::class, 'destroy']);

    /* ---------- Locations & categories (reads public above) ---------- */
    Route::post('/locations/sectors', [LocationController::class, 'storeSector']);
    Route::post('/locations/blocks', [LocationController::class, 'storeBlock']);
    Route::post('/locations/categories', [LocationController::class, 'storeCategory']);

    /* ---------- Subscriptions (agency view) ---------- */
    Route::get('/agency/subscription', [SubscriptionController::class, 'current']);

    /* ---------- Admin: dashboard analytics ---------- */
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

    /* ---------- Admin: agencies ---------- */
    Route::get('/admin/agencies', [AdminAgencyController::class, 'index']);
    Route::get('/admin/agencies/{id}', [AdminAgencyController::class, 'show']);
    Route::match(['put', 'patch'], '/admin/agencies/{id}/verify', [AdminAgencyController::class, 'verify']);
    Route::match(['put', 'patch'], '/admin/agencies/{id}/status', [AdminAgencyController::class, 'status']);
    Route::get('/admin/plans', [AdminAgencyController::class, 'plans']);
    Route::post('/admin/plans', [AdminAgencyController::class, 'storePlan']);
    Route::match(['put', 'patch'], '/admin/plans/{id}', [AdminAgencyController::class, 'updatePlan']);
    Route::put('/admin/agencies/{id}/subscription', [AdminAgencyController::class, 'assignSubscription']);

    /* ---------- Admin: users & roles ---------- */
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::match(['put', 'patch'], '/admin/users/{id}', [AdminUserController::class, 'update']);
    Route::post('/admin/users/{id}/unlock', [AdminUserController::class, 'unlock']);
    Route::post('/admin/users/{id}/revoke-tokens', [AdminUserController::class, 'revokeTokens']);
    Route::get('/admin/roles', [AdminUserController::class, 'roles']);

    /* ---------- Admin: audit trail & system settings ---------- */
    Route::get('/admin/audit-logs', [AdminSystemController::class, 'auditLogs']);
    Route::get('/admin/settings', [AdminSystemController::class, 'settings']);
    Route::match(['put', 'patch'], '/admin/settings', [AdminSystemController::class, 'updateSetting']);
});
