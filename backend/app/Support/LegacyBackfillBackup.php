<?php

namespace App\Support;

use RuntimeException;

/**
 * Integrity gate for database dumps accepted by `platform:legacy-backfill`.
 *
 * `is_readable()` alone accepts empty files, half-written dumps, unrelated SQL
 * files and even source files. This verifier checks that the dump is non-empty,
 * decompresses cleanly when gzipped, actually contains the `business_records`
 * table this command mutates, and produces a stable SHA-256 that is recorded
 * in the rollback journal for later comparison.
 */
final class LegacyBackfillBackup
{
    private const SQLITE_HEADER = "SQLite format 3\x00";

    private string $sha256 = '';
    private string $format = '';
    private int $tableCount = 0;

    private function __construct(private readonly string $path) {}

    public static function verify(string $path): self
    {
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException("Backup is not a file: {$path}");
        }
        if (!is_readable($path)) {
            throw new RuntimeException("Backup is not readable: {$path}");
        }

        $size = @filesize($path);
        if ($size === false || $size < 64) {
            throw new RuntimeException('Backup is empty, too small (' . ($size === false ? 'unknown' : $size) . ' bytes) or truncated.');
        }

        $sha = @hash_file('sha256', $path);
        if ($sha === false) {
            throw new RuntimeException('Could not checksum backup.');
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Could not read backup.');
        }

        // scripts/backup.sh emits gzipped artefacts, so decompress transparently.
        $isGzip = str_starts_with($raw, "\x1f\x8b") || str_ends_with(strtolower($path), '.gz');
        if ($isGzip) {
            if (!function_exists('gzdecode')) {
                throw new RuntimeException('gzip backup cannot be verified: zlib/gzdecode is unavailable.');
            }
            $decoded = @gzdecode($raw);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('Backup gzip stream is corrupt or truncated.');
            }
        } else {
            $decoded = $raw;
        }

        $backup = new self($path);
        $backup->sha256 = $sha;

        if (str_starts_with($decoded, self::SQLITE_HEADER)) {
            // Raw SQLite copy: its schema pages hold plaintext DDL, so the
            // table name must be present and the file must span at least one
            // database page (512 bytes minimum).
            if (strlen($decoded) < 512 || !str_contains($decoded, 'business_records')) {
                throw new RuntimeException('SQLite backup does not contain a business_records table.');
            }
            $backup->format = 'sqlite-database-copy';
            $backup->tableCount = max(1, substr_count($decoded, 'CREATE TABLE'));

            return $backup;
        }

        if (!preg_match('/\bCREATE\s+TABLE\b/i', $decoded)) {
            throw new RuntimeException('Backup is neither a SQL dump nor a SQLite database copy.');
        }

        // The table being stamped must be present; otherwise this is an
        // unrelated dump and the rollback promise would be meaningless.
        $hasTable = preg_match('/\bCREATE\s+TABLE\s+(?:`business_records`|"business_records"|\[business_records\]|business_records)(?![A-Za-z0-9_])/i', $decoded)
            || preg_match('/\bCREATE\s+TABLE\s+[^\s(]+\s*\.\s*(?:`business_records`|"business_records"|\[business_records\]|business_records)(?![A-Za-z0-9_])/i', $decoded);
        if (!$hasTable) {
            throw new RuntimeException('SQL backup does not contain a business_records table.');
        }

        // mysqldump marks a completed dump; its absence means a truncated file.
        if (stripos($decoded, 'mysqldump') !== false && stripos($decoded, 'Dump completed') === false) {
            throw new RuntimeException('mysqldump backup is missing its "Dump completed" marker (likely truncated).');
        }

        $backup->format = 'sql-dump';
        $backup->tableCount = preg_match_all('/\bCREATE\s+TABLE\b/i', $decoded);

        return $backup;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function tableCount(): int
    {
        return $this->tableCount;
    }

    public function summary(): string
    {
        return sprintf('%s (%s, %d tables, sha256 %s)', basename($this->path), $this->format, $this->tableCount, $this->sha256);
    }
}
