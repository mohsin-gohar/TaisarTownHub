<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\DealerController;
use App\Http\Controllers\Api\AgencyVideoController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ProjectController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'application' => 'TaisarTownHub Laravel API',
        'database' => 'MySQL connected',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Properties
Route::apiResource('properties', PropertyController::class);

// Agencies & Dealers
Route::apiResource('dealers', DealerController::class);

// Agency Video Showcase & Reels
Route::get('videos', [AgencyVideoController::class, 'index']);
Route::get('videos/{id}', [AgencyVideoController::class, 'show']);
Route::post('videos', [AgencyVideoController::class, 'store']);
Route::post('videos/{id}/like', [AgencyVideoController::class, 'like']);
Route::post('videos/{id}/comments', [AgencyVideoController::class, 'comment']);

// Inquiries CRM
Route::get('inquiries', [InquiryController::class, 'index']);
Route::post('inquiries', [InquiryController::class, 'store']);
Route::patch('inquiries/{id}/status', [InquiryController::class, 'updateStatus']);

// Transactions & Projects
Route::apiResource('transactions', TransactionController::class)->only(['index', 'store']);
Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
