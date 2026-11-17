<?php

/**
 * CI readiness probe for the MySQL service container.
 * All connection details come from environment variables — no shell quoting
 * of PHP variables (which broke the earlier `php -r "$pdo = ..."` step).
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$database}";

$lastError = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo "MySQL ready after {$attempt} attempt(s): {$version}\n";
        exit(0);
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        sleep(1);
    }
}

fwrite(STDERR, "MySQL did not become ready: {$lastError}\n");
exit(1);
