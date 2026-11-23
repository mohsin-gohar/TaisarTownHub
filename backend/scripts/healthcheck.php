<?php

/**
 * PHP-FPM healthcheck for Docker container.
 * 
 * This script checks if the PHP-FPM process is responding correctly.
 * It can be run via CLI for Docker healthcheck or called via HTTP if needed.
 * 
 * Exit codes:
 *   0 = healthy
 *   1 = unhealthy
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check database connectivity
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->query('SELECT 1')->fetchColumn();
    
    // Check cache connectivity
    Illuminate\Support\Facades\Cache::get('healthcheck');
    
    // Check if storage is writable
    $storagePath = storage_path('logs');
    if (!is_writable($storagePath)) {
        throw new Exception("Storage path not writable: {$storagePath}");
    }
    
    // All checks passed
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'checks' => [
            'database' => 'ok',
            'cache' => 'ok',
            'storage' => 'ok',
        ],
    ]);
    exit(0);
    
} catch (Throwable $e) {
    fwrite(STDERR, "Healthcheck failed: " . $e->getMessage() . "\n");
    echo json_encode([
        'status' => 'unhealthy',
        'timestamp' => date('c'),
        'error' => $e->getMessage(),
    ]);
    exit(1);
}