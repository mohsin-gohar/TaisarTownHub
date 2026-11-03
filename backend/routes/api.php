<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\DealerController;
use App\Http\Controllers\Api\AgencyVideoController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\DealerFilesController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'application' => 'TaisarTownHub Laravel API',
        'database' => 'MySQL connected',
        'timestamp' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication (Sanctum)
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| Properties
|--------------------------------------------------------------------------
*/
Route::apiResource('properties', PropertyController::class);

/*
|--------------------------------------------------------------------------
| Agencies & Dealers
|--------------------------------------------------------------------------
*/
Route::apiResource('dealers', DealerController::class);

/*
|--------------------------------------------------------------------------
| Dealer File Management
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Dealer's own files
    Route::get('dealer-files', [DealerFilesController::class, 'index']);
    Route::post('dealer-files', [DealerFilesController::class, 'store']);

    // Mark file as sold + calculate commission
    Route::post('dealer-files/{id}/sell', [DealerFilesController::class, 'sell']);

    // Sales attribution report for a specific dealer
    Route::get('dealers/{dealerId}/sales-report', [DealerFilesController::class, 'salesReport']);

    // System-wide sales report (super admin)
    Route::get('sales-report', [DealerFilesController::class, 'allSalesReport']);
});

/*
|--------------------------------------------------------------------------
| Agency Video Showcase & Reels
|--------------------------------------------------------------------------
*/
Route::get('videos', [AgencyVideoController::class, 'index']);
Route::get('videos/{id}', [AgencyVideoController::class, 'show']);
Route::post('videos', [AgencyVideoController::class, 'store'])->middleware('throttle:10,1');
Route::post('videos/{id}/like', [AgencyVideoController::class, 'like'])->middleware('throttle:30,1');
Route::post('videos/{id}/comments', [AgencyVideoController::class, 'comment'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Inquiries CRM
|--------------------------------------------------------------------------
*/
Route::get('inquiries', [InquiryController::class, 'index']);
Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');
Route::patch('inquiries/{id}/status', [InquiryController::class, 'updateStatus']);

/*
|--------------------------------------------------------------------------
| Transactions & Projects
|--------------------------------------------------------------------------
*/
Route::apiResource('transactions', TransactionController::class)->only(['index', 'store']);
Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);