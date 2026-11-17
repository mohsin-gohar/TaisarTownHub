<?php

/**
 * Minimal HTTP worker used by MysqlConcurrencyTest.
 *
 * It boots the real Laravel application and dispatches an in-process request
 * through the HTTP kernel, so two OS processes can hit the same database at
 * the same time. Database credentials are inherited from the environment.
 *
 * Usage: php tests/Support/concurrency_request.php METHOD URI JSON BODY TOKEN
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';

// Fail fast if a locked read has to wait, so deadlocks surface as responses
// instead of hanging a CI job.
try {
    $app->make('db')->statement('SET SESSION innodb_lock_wait_timeout = 5');
} catch (\Throwable $e) {
    // Non-MySQL drivers ignore this; the test handles those paths separately.
}

$method = $argv[1];
$uri = $argv[2];
$body = $argv[3] ?? '{}';
$token = $argv[4] ?? '';

$server = [
    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
];

// Pass JSON as the raw request body so Laravel's JSON input source parses it.
$request = Illuminate\Http\Request::create($uri, $method, [], [], [], $server, $body);
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
]);
