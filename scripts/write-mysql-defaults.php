<?php

declare(strict_types=1);

$root = rtrim($argv[1] ?? '', DIRECTORY_SEPARATOR);
$backend = rtrim($argv[2] ?? '', DIRECTORY_SEPARATOR);
$defaultsPath = $argv[3] ?? '';
$metadataPath = $argv[4] ?? '';

if ($root === '' || $backend === '' || $defaultsPath === '' || $metadataPath === '') {
    fwrite(STDERR, "[backup] ERROR: usage: write-mysql-defaults.php ROOT BACKEND MYSQL_DEFAULTS META_FILE\n");
    exit(2);
}

// Parse the backend .env first, then the root Compose .env so Compose values
// override local Laravel defaults. Process environment variables still win.
$parsed = [];
$parseEnv = static function (string $path) use (&$parsed): void {
    if (! is_file($path) || ! is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
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
};

$parseEnv($backend . DIRECTORY_SEPARATOR . '.env');
$parseEnv($root . DIRECTORY_SEPARATOR . '.env');

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
