<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentQuarantine;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use App\Services\FileVaultCrypto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-shot (idempotent) migration command: re-writes every vault file that is
 * still stored as plaintext with AES-256-GCM at-rest encryption.
 *
 * Reads are always transparent — `SecureFileStore::get()` detects the
 * envelope — so this command can run live without downtime:
 *   php artisan vault:encrypt          # documents + versions + media
 *   php artisan vault:encrypt --disk=documents|quarantine
 *
 * For APP_KEY rotation, keep the old key in APP_PREVIOUS_KEYS while running
 * this command: files readable with a previous key are re-encrypted with the
 * active key. Files that fail to decrypt (written
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

    /**
     * Stage and authenticate the replacement before moving it over the
     * original object. A failed write/verification leaves the original bytes
     * untouched, including during APP_KEY rotation.
     */
    private function writeVerifiedEnvelope(string $disk, string $path, string $plain): void
    {
        $storage = Storage::disk($disk);
        $temporaryPath = $path . '.vault-rotation-' . Str::random(16);

        try {
            if (! $storage->put($temporaryPath, FileVaultCrypto::encrypt($plain))) {
                throw new \RuntimeException('Could not write the staged encrypted object.');
            }

            $staged = (string) $storage->get($temporaryPath);
            if (! FileVaultCrypto::isEncryptedWithCurrentKey($staged)
                || FileVaultCrypto::decrypt($staged) !== $plain) {
                throw new \RuntimeException('Staged encrypted object failed current-key verification.');
            }

            if (! $storage->move($temporaryPath, $path)) {
                throw new \RuntimeException('Could not replace the original vault object.');
            }
        } finally {
            if ($storage->exists($temporaryPath)) {
                $storage->delete($temporaryPath);
            }
        }
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
                if (FileVaultCrypto::isEncryptedWithCurrentKey($blob)) {
                    $counts['already']++;
                    continue;
                }

                try {
                    $plain = FileVaultCrypto::decrypt($blob);
                } catch (\Throwable $e) {
                    $counts['errors']++;
                    $this->error("DECRYPT FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");
                    continue;
                }
            } else {
                $plain = $blob;
            }

            if ($dry) {
                $counts['encrypted']++;
                continue;
            }

            try {
                $this->writeVerifiedEnvelope($disk, $path, $plain);
                $counts['encrypted']++;
            } catch (\Throwable $e) {
                $counts['errors']++;
                $this->error("WRITE FAILED [{$disk}] id={$row->id}: {$e->getMessage()}");
            }
        }
    }
}
