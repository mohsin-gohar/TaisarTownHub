<?php

declare(strict_types=1);

$backend = rtrim($argv[1] ?? '', DIRECTORY_SEPARATOR);
$defaultsPath = $argv[2] ?? '';
$metadataPath = $argv[3] ?? '';

if ($backend === '' || $defaultsPath === '' || $metadataPath === '') {
    fwrite(STDERR, "[backup] ERROR: usage: write-mysql-defaults.php BACKEND MYSQL_DEFAULTS META_FILE\n");
    exit(2);
}

require $backend . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable($backend)->safeLoad();

$get = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : (string) $value;
};

$driver = $get('DB_CONNECTION', 'mysql');
$database = $get('DB_DATABASE');
$host = $get('DB_HOST', '127.0.0.1');
$port = $get('DB_PORT', '3306');
$user = $get('DB_USERNAME');
$password = $get('DB_PASSWORD');

if ($database === '' || $user === '') {
    fwrite(STDERR, "[backup] FATAL: DB_DATABASE and DB_USERNAME must be configured.\n");
    exit(2);
}

$escape = static fn (string $value): string => '"' . addcslashes($value, "\\\"\n\r") . '"';
$config = "[client]\n"
    . "host=" . $escape($host) . "\n"
    . "port=" . $escape($port) . "\n"
    . "user=" . $escape($user) . "\n"
    . "password=" . $escape($password) . "\n";

if (file_put_contents($defaultsPath, $config, LOCK_EX) === false || ! chmod($defaultsPath, 0600)) {
    fwrite(STDERR, "[backup] FATAL: could not create protected MySQL option file.\n");
    exit(2);
}

$metadata = implode("\n", [$driver, $database, $host, $port, $user]) . "\n";
if (file_put_contents($metadataPath, $metadata, LOCK_EX) === false) {
    @unlink($defaultsPath);
    fwrite(STDERR, "[backup] FATAL: could not write database metadata.\n");
    exit(2);
}

exit(0);
