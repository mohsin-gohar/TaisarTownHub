<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Shared upload content-security checks for the Secure Document & Media
 * Vault. Every byte upload (documents, versions, media) funnels through
 * `evaluate()`:
 *
 *   1. extension ALLOWLIST (config-driven; nothing unknown is accepted),
 *   2. sniffed MIME blocklist (never the browser-declared type),
 *   3. magic-byte check over the FIRST bytes of the file (offset 0) so a
 *      PHP script / Windows binary / ELF wearing a `.pdf` name is rejected
 *      even when the sniffed MIME is generic.
 *
 * The malware scan itself runs afterwards through App\Services\MalwareScanner
 * so each caller can apply its own quarantine / fail-closed policy.
 */
class UploadSecurity
{
    /** MIME types that must never be accepted regardless of extension. */
    public static function dangerousMimes(): array
    {
        return [
            'application/x-php',
            'application/x-httpd-php',
            'application/x-httpd-php-source',
            'text/html',
            'application/xhtml+xml',
            'application/x-python-code',
            'application/x-python',
            'text/x-python',
            'application/x-ruby',
            'application/x-sh',
            'application/x-shellscript',
            'application/x-perl',
            'application/x-csh',
            'text/javascript',
            'application/javascript',
            'application/ecmascript',
            'application/x-msdownload',
            'application/x-ms-executable',
            'application/x-dosexec',
            'application/x-executable',
            'application/x-elf',
        ];
    }

    public static function isDangerousMime(string $mime): bool
    {
        return in_array(strtolower($mime), self::dangerousMimes(), true);
    }

    /**
     * Inspect the FIRST bytes of the file (offset 0, 32 bytes) for clearly
     * dangerous executable/script signatures. This is a secondary gate after
     * the extension allowlist: unreadable/unknown content is allowed here and
     * left to the malware scan, but a file that IS an executable or script can
     * never slip through by carrying an allowed extension.
     */
    public static function magicBytesLookDangerous(string $path): bool
    {
        // NOTE: offset 0, length 32. (A previous version passed 32 as the
        // OFFSET — the first 32 bytes were never inspected, so a leading
        // "MZ"/"<?php"/ELF header was invisible to this check.)
        $bytes = @file_get_contents($path, false, null, 0, 32);

        if ($bytes === false || $bytes === '') {
            return false; // unreadable/empty: the allowlist is the guard
        }

        $dangerous = [
            '<?php',              // PHP script
            '<?',                 // PHP short tag / XML preamble
            "\x7fELF",            // ELF binary
            'MZ',                 // Windows PE (exe/dll)
            '#!',                 // shebang script
        ];

        foreach ($dangerous as $sig) {
            if (str_starts_with($bytes, $sig)) {
                return true;
            }
        }

        return false;
    }

    /** Config-driven extension allowlist for vault documents. */
    public static function allowedExtensions(): array
    {
        return array_map('strtolower', (array) SystemSetting::get('document.allowed_extensions', [
            'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mov',
        ]));
    }

    /**
     * Run the extension/MIME/magic-byte pipeline on an uploaded file.
     * Returns ['error' => JsonResponse|null, 'ext' => string, 'mime' => string].
     */
    public static function evaluate(UploadedFile $file, array $allowedExt): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, array_map('strtolower', $allowedExt), true)) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => "File type .{$ext} is not allowed.",
                ], 422),
                'ext' => $ext,
                'mime' => '',
            ];
        }

        // Server-side MIME inspection — the browser-declared type is ignored.
        $realMime = @mime_content_type($file->getRealPath()) ?: 'application/octet-stream';

        if (self::isDangerousMime($realMime)) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => "File type {$realMime} is not allowed.",
                ], 422),
                'ext' => $ext,
                'mime' => $realMime,
            ];
        }

        if (self::magicBytesLookDangerous($file->getRealPath())) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'File content is corrupted or does not match its type.',
                ], 422),
                'ext' => $ext,
                'mime' => $realMime,
            ];
        }

        return ['error' => null, 'ext' => $ext, 'mime' => $realMime];
    }

    /** Neutralise a client-supplied filename before it is stored or echoed. */
    public static function sanitizeOriginalName(?string $name): string
    {
        $name = strip_tags((string) $name);
        // Remove header-breaking and path characters; keep readable text.
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/', '_', $name) ?? '';

        return mb_substr(trim($name), 0, 255);
    }

    /**
     * External media URLs may only be HTTPS public hosts — no credentials,
     * no localhost/private-network targets (SSRF-safe redirect source).
     */
    public static function isAllowedExternalUrl(string $url): bool
    {
        if (!str_starts_with(strtolower(trim($url)), 'https://')) {
            return false;
        }

        $parts = parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false; // credentials never belong in shared URLs
        }

        $host = strtolower($parts['host']);

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // IP-literal hosts must be public.
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        return true;
    }
}
