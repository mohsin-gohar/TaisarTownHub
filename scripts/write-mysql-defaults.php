<?php

declare(strict_types=1);

$backend = rtrim($argv[1] ?? '', DIRECTORY_SEPARATOR);
$defaultsPath = $argv[2] ?? '';
$metadataPath = $argv[3] ?? '';

if ($backend === '' || $defaultsPath === '' || $metadataPath === '') {
    fwrite(STDERR, "[backup] ERROR: usage: write-mysql-defaults.php BACKEND MYSQL_DEFAULTS META_FILE\n");
    exit(2);
}

// Parse the small, single-line subset of Laravel .env needed by mysqldump.
// This helper deliberately has no Composer/vendor dependency so it also runs
// on Docker hosts where PHP dependencies exist only inside the application image.
$parsed = [];
$envPath = $backend . DIRECTORY_SEPARATOR . '.env';
if (! is_file($envPath) || ! is_readable($envPath)) {
    fwrite(STDERR, "[backup] FATAL: backend/.env is missing or unreadable.\n");
    exit(2);
}

foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (str_starts_with($line, 'export ')) {
        $line = ltrim(substr($line, 7));
    }
    if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
        continue;
    }

    $key = $matches[1];
    $raw = trim($matches[2]);
    if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
        $value = stripcslashes(substr($raw, 1, -1));
    } elseif (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
        $value = substr($raw, 1, -1);
    } else {
        $comment = strpos($raw, ' #');
        if ($comment !== false) {
            $raw = substr($raw, 0, $comment);
        }
        $value = trim($raw);
    }
    $parsed[$key] = $value;
}

$get = static function (string $key, string $default = '') use ($parsed): string {
    $value = getenv($key);
    if ($value === false && array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    }
    if ($value === false && array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
    }
    if ($value === false || $value === null) {
        $value = $parsed[$key] ?? $default;
    }
    return (string) $value;
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
