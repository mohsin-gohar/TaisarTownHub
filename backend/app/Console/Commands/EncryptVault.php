<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentQuarantine;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use App\Services\FileVaultCrypto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-shot (idempotent) migration command: re-writes every vault file that is
 * still stored as plaintext with AES-256-GCM at-rest encryption.
 *
 * Reads are always transparent — `SecureFileStore::get()` detects the
 * envelope — so this command can run live without downtime:
 *   php artisan vault:encrypt          # documents + versions + media
 *   php artisan vault:encrypt --disk=documents|quarantine
 *
 * Run it again after an APP_KEY rotation: files still readable under the OLD
 * key are re-encrypted with the new one. Files that fail to decrypt (written
 * under an unknown key) are reported and left untouched — never overwritten.
 */
class EncryptVault extends Command
{
    protected $signature = 'vault:encrypt {--disk= : Restrict to documents|quarantine} {--dry-run : Report without writing}';

    protected $description = 'Encrypt plaintext vault files at rest (idempotent; safe after APP_KEY rotation)';

    public function handle(): int
    {
        $only = $this->option('disk');
        $dry = (bool) $this->option('dry-run');
        $counts = ['encrypted' => 0, 'already' => 0, 'errors' => 0];

        if ($only === null || $only === 'documents') {
            $this->processDisk('documents', Document::withTrashed()->get(), fn ($r) => $r->disk_path, $counts, $dry);
            $this->processDisk('documents', DocumentVersion::query()->get(), fn ($r) => $r->disk_path, $counts, $dry);
            $this->processDisk('documents', MediaFile::withTrashed()->whereNotNull('disk_path')->get(), fn ($r) => $r->disk_path, $counts, $dry);
        }

        if ($only === null || $only === 'quarantine') {
            $this->processDisk('quarantine', DocumentQuarantine::query()->get(), fn ($r) => $r->disk_path, $counts, $dry);
        }

        $this->info("vault:encrypt done — encrypted: {$counts['encrypted']}, already encrypted: {$counts['already']}, errors: {$counts['errors']}" . ($dry ? ' (dry run)' : ''));

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processDisk(string $disk, $rows, \Closure $pathOf, array &$counts, bool $dry): void
    {
        foreach ($rows as $row) {
            $path = $pathOf($row);
            if (!$path || !Storage::disk($disk)->exists($path)) {
                continue;
            }

            try {
                $blob = (string) Storage::disk($disk)->get($path);
            } catch (\Throwable $e) {
                $counts['errors']++;
                $this->error("READ FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");

                continue;
            }

            if (FileVaultCrypto::isEncrypted($blob)) {
                try {
                    $plain = FileVaultCrypto::decrypt($blob);
                } catch (\Throwable $e) {
                    $counts['errors']++;
                    $this->error("DECRYPT FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");
                    continue;
                }

                if (FileVaultCrypto::isEncryptedWithCurrentKey($blob)) {
                    $counts['already']++;
                    continue;
                }

                // The previous APP_KEY authenticated this envelope. Re-encrypt
                // it with the active key while the previous key remains configured.
                if ($dry) {
                    $counts['encrypted']++;
                    continue;
                }

                try {
                    if (!Storage::disk($disk)->put($path, FileVaultCrypto::encrypt($plain))) {
                        throw new \RuntimeException('Storage adapter returned false.');
                    }
                    $counts['encrypted']++;
                } catch (\Throwable $e) {
                    $counts['errors']++;
                    $this->error("WRITE FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");
                }

                continue;
            }

            if ($dry) {
                $counts['encrypted']++;
                continue;
            }

            try {
                if (!Storage::disk($disk)->put($path, FileVaultCrypto::encrypt($blob))) {
                    throw new \RuntimeException('Storage adapter returned false.');
                }
                $counts['encrypted']++;
            } catch (\Throwable $e) {
                $counts['errors']++;
                $this->error("WRITE FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");
            }
        }
    }
}
