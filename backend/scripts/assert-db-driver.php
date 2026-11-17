<?php

/**
 * CI guard: fail fast if the integration job is accidentally using SQLite.
 * Boots the real Laravel application so config/env resolution is identical
 * to the test process.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$driver = Illuminate\Support\Facades\DB::connection()->getDriverName();
$database = Illuminate\Support\Facades\DB::connection()->getDatabaseName();

echo "DB_CONNECTION={$driver}\n";
echo "DB_DATABASE={$database}\n";

if ($driver !== 'mysql') {
    fwrite(STDERR, "ERROR: MySQL integration job is not using MySQL (got {$driver}).\n");
    exit(1);
}

$version = Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() as v')->v ?? 'unknown';
echo "MYSQL_VERSION={$version}\n";
