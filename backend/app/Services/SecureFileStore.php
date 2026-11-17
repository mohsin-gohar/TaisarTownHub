<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $plain = (string) file_get_contents($file->getRealPath());
        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;

        Storage::disk($disk)->put($path, $blob);

        return strlen($plain);
    }

    /** Copy raw (possibly plaintext) bytes onto a disk with vault encryption. */
    public static function putBytes(string $disk, string $path, string $plain): int
    {
        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;
        Storage::disk($disk)->put($path, $blob);

        return strlen($plain);
    }

    /** Move bytes between disks (re-encrypting) — used by quarantine promotion. */
    public static function move(string $fromDisk, string $fromPath, string $toDisk, string $toPath): void
    {
        $plain = FileVaultCrypto::decrypt((string) Storage::disk($fromDisk)->get($fromPath));
        $blob = FileVaultCrypto::enabled() ? FileVaultCrypto::encrypt($plain) : $plain;
        Storage::disk($toDisk)->put($toPath, $blob);
        Storage::disk($fromDisk)->delete($fromPath);
    }

    /** Read plaintext bytes transparently (decrypts when the envelope is present). */
    public static function get(string $disk, string $path): string
    {
        return FileVaultCrypto::decrypt((string) Storage::disk($disk)->get($path));
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

        file_put_contents($temp, self::get($disk, $path));

        try {
            return $scanner->scan($temp);
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
                echo FileVaultCrypto::decrypt((string) Storage::disk($disk)->get($path));
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
