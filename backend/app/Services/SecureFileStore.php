<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

/**
 * Secure Document & Media Vault — the single gateway for writing private
 * bytes to disk and reading them back.
 *
 * Every write:
 *   - stores the file on a private disk (`documents` / `quarantine` — both
 *     outside the web root, never symlinked, `serve => false`),
 *   - encrypts it at rest with AES-256-GCM when vault encryption is enabled
 *     (App\Services\FileVaultCrypto),
 *   - never exposes the physical path: callers only keep the relative
 *     disk path in the database (hidden from API serialization).
 *
 * Every read:
 *   - goes through `response()` after the caller has authenticated the user,
 *     re-checked tenant scope + permissions, and written audit rows,
 *   - transparently decrypts (legacy plaintext keeps working).
 */
class SecureFileStore
{
    /** Encrypt-on-write for uploaded files. Returns the logical (plaintext) size. */
    public static function storeUpload(string $disk, string $path, UploadedFile $file): int
    {
        $realPath = $file->getRealPath();
        if (!is_string($realPath) || !is_file($realPath) || !is_readable($realPath)) {
            throw new RuntimeException('Uploaded file could not be read from temporary storage.');
        }

        $plain = file_get_contents($realPath);
        if ($plain === false) {
            throw new RuntimeException('Uploaded file could not be read from temporary storage.');
        }

        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;
        if (!Storage::disk($disk)->put($path, $blob)) {
            throw new RuntimeException('Vault storage write failed; upload was not committed.');
        }

        return strlen($plain);
    }

    /** Copy raw (possibly plaintext) bytes onto a disk with vault encryption. */
    public static function putBytes(string $disk, string $path, string $plain): int
    {
        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;
        if (!Storage::disk($disk)->put($path, $blob)) {
            throw new RuntimeException('Vault storage write failed; bytes were not committed.');
        }

        return strlen($plain);
    }

    /** Move bytes between disks (re-encrypting) — used by quarantine promotion. */
    public static function move(string $fromDisk, string $fromPath, string $toDisk, string $toPath): void
    {
        $source = Storage::disk($fromDisk);
        $target = Storage::disk($toDisk);

        if (!$source->exists($fromPath)) {
            throw new RuntimeException('Vault source file is missing; move was not performed.');
        }

        $stored = $source->get($fromPath);
        if (!is_string($stored)) {
            throw new RuntimeException('Vault source file could not be read; move was not performed.');
        }

        $plain = FileVaultCrypto::decrypt($stored);
        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;
        if (!$target->put($toPath, $blob)) {
            throw new RuntimeException('Vault destination write failed; source file was preserved.');
        }

        if (!$source->delete($fromPath)) {
            // Do not silently leave two competing copies. If cleanup also
            // fails, log it; the source remains authoritative until DB commit.
            if (!$target->delete($toPath)) {
                Log::critical('Vault move destination cleanup failed after source delete failure', [
                    'from_disk' => $fromDisk, 'from_path_len' => strlen($fromPath),
                    'to_disk' => $toDisk, 'to_path_len' => strlen($toPath),
                ]);
            }
            throw new RuntimeException('Vault source delete failed; move was not committed.');
        }
    }

    /** Read plaintext bytes transparently (decrypts when the envelope is present). */
    public static function get(string $disk, string $path): string
    {
        $stored = Storage::disk($disk)->get($path);
        if (!is_string($stored)) {
            throw new RuntimeException('Vault file could not be read from storage.');
        }

        return FileVaultCrypto::decrypt($stored);
    }

    /** SHA-256 of the plaintext content (integrity checksum stored in DB). */
    public static function checksum(string $disk, string $path): ?string
    {
        $plain = self::get($disk, $path);

        return hash('sha256', $plain) ?: null;
    }

    /**
     * Scan bytes already stored in the vault through the malware scanner.
     * The scanner needs a real file path, so a temporary decrypted copy is
     * written to the OS temp dir and erased immediately afterwards — the
     * temp location is outside the web root and on the same machine.
     */
    public static function scanStored(string $disk, string $path, MalwareScanner $scanner): \App\Support\MalwareScanResult
    {
        $temp = tempnam(sys_get_temp_dir(), 'tthscan_');
        if ($temp === false) {
            return \App\Support\MalwareScanResult::skipped('clamav', 'temp_file_unavailable');
        }

        try {
            $plain = self::get($disk, $path);
            $written = @file_put_contents($temp, $plain);
            if ($written === false || $written !== strlen($plain)) {
                return \App\Support\MalwareScanResult::skipped('clamav', 'temp_file_write_failed');
            }

            return $scanner->scan($temp);
        } catch (\Throwable $exception) {
            Log::warning('Vault rescan could not materialize a readable plaintext file', [
                'disk' => $disk,
                'path_len' => strlen($path),
                'error' => $exception->getMessage(),
            ]);

            return \App\Support\MalwareScanResult::skipped('clamav', 'vault_read_failed');
        } finally {
            @unlink($temp);
        }
    }

    /**
     * Authorised streaming response. Call ONLY after the caller has:
     * authenticated the user, re-checked agency scope + record-level
     * permissions, and written the access-log / audit rows.
     *
     * Headers: server-verified Content-Type, nosniff, and a CRLF-safe
     * Content-Disposition (Laravel builds it from the $disposition argument;
     * the name is pre-sanitised so a hostile filename can never split the
     * header).
     */
    public static function response(
        string $disk,
        string $path,
        string $downloadName,
        string $mime,
        string $disposition
    ): StreamedResponse|JsonResponse {
        if (!Storage::disk($disk)->exists($path)) {
            Log::warning('Vault file missing on disk', ['disk' => $disk, 'path_len' => strlen($path)]);

            return response()->json([
                'success' => false,
                'message' => 'File is no longer available on storage.',
            ], 410);
        }

        $safeName = self::safeDispositionName($downloadName);

        return response()->streamDownload(
            function () use ($disk, $path) {
                echo self::get($disk, $path);
            },
            $safeName,
            [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
            ],
            $disposition === 'inline' ? 'inline' : 'attachment'
        );
    }

    /** Strip header-breaking characters (CR/LF/control/quotes) from a filename. */
    public static function safeDispositionName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\]/', '_', $name) ?? 'download';

        return $clean !== '' ? mb_substr($clean, 0, 200) : 'download';
    }
}
