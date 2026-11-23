<?php

/**
 * Container readiness probe for the Laravel API.
 *
 * Verifies the dependencies the service actually needs to serve traffic:
 * MySQL connectivity, a real Redis cache round-trip (Compose runs
 * CACHE_STORE=redis, SESSION_DRIVER=redis and QUEUE_CONNECTION=redis), and a
 * writable storage/log directory. Failure prints the failing dependency to
 * stderr so `docker compose logs` explains WHY the container is unhealthy.
 *
 * Exit codes: 0 = healthy, 1 = unhealthy.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$checks = [];

try {
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->query('SELECT 1')->fetchColumn();
    $checks['database'] = 'ok';
} catch (Throwable $e) {
    fwrite(STDERR, "Healthcheck failed [database]: " . $e->getMessage() . "\n");
    echo json_encode(['status' => 'unhealthy', 'checks' => $checks, 'error' => 'database: ' . $e->getMessage()]);
    exit(1);
}

try {
    $key = 'healthcheck:' . gethostname();
    Illuminate\Support\Facades\Cache::put($key, 1, now()->addSeconds(30));
    if (Illuminate\Support\Facades\Cache::get($key) !== 1) {
        throw new RuntimeException('cache round-trip did not return the stored value');
    }
    $checks['cache'] = 'ok';
} catch (Throwable $e) {
    fwrite(STDERR, "Healthcheck failed [cache]: " . $e->getMessage() . "\n");
    echo json_encode(['status' => 'unhealthy', 'checks' => $checks, 'error' => 'cache: ' . $e->getMessage()]);
    exit(1);
}

$storagePath = storage_path('logs');
if (!is_writable($storagePath)) {
    fwrite(STDERR, "Healthcheck failed [storage]: {$storagePath} is not writable\n");
    echo json_encode(['status' => 'unhealthy', 'checks' => $checks, 'error' => 'storage: not writable']);
    exit(1);
}
$checks['storage'] = 'ok';

echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => $checks,
]);
exit(0);
