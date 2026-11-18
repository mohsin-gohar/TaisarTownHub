<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Liveness/readiness probe used by uptime monitors and the deploy gate.
 * A response of 503 means the API is up but cannot serve traffic.
 */
class HealthController
{
    public function index(): JsonResponse
    {
        $database = ucfirst(DB::connection()->getDriverName()) . ' connected';
        $status = 'healthy';
        $httpStatus = 200;

        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            Log::error('Health check database failure', ['error' => $e->getMessage()]);
            $database = 'unavailable';
            $status = 'degraded';
            $httpStatus = 503;
        }

        return response()->json([
            'status' => $status,
            'application' => 'TaisarTownHub Laravel API',
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ], $httpStatus);
    }
}
