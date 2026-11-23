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
    // Laravel stores numeric cache values without serialization, so a Redis
    // round-trip of `1` may return the string "1". Use a non-numeric sentinel
    // and compare it as a string to avoid a false "unhealthy" result.
    $sentinel = 'ok-' . gethostname();
    Illuminate\Support\Facades\Cache::put($key, $sentinel, now()->addSeconds(30));
    $cached = Illuminate\Support\Facades\Cache::get($key);
    if (!is_string($cached) || $cached !== $sentinel) {
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
