<?php

namespace App\Services;

use RuntimeException;

/**
 * At-rest encryption for the Secure Document & Media Vault.
 *
 * Algorithm: AES-256-GCM (authenticated encryption) via the standard
 * OpenSSL integration PHP ships with — no custom cryptography.
 *
 * Key management: the data key is a SHA-256 subkey derived from APP_KEY with
 * a vault-specific domain separator. APP_KEY itself comes from the
 * environment (.env / secret store) and is never stored near the files.
 * Rotating APP_KEY therefore rotates the vault key; use
 * `php artisan vault:encrypt` / re-running it after a key change to
 * re-encrypt stored bytes (legacy plaintext keeps reading transparently).
 *
 * Envelope format (single write, streaming-friendly):
 *   "TTHENC1\n" . base64(iv) . "\n" . base64(tag) . "\n" . ciphertext
 *
 * Anything that does not start with the envelope marker is treated as legacy
 * plaintext and returned as-is, so files written before encryption was
 * enabled keep working without a migration downtime.
 */
class FileVaultCrypto
{
    /** Envelope marker for encrypted vault payloads. */
    public const ENVELOPE = "TTHENC1\n";

    /** Domain separator so the vault key is distinct from other APP_KEY uses. */
    private const KEY_CONTEXT = 'taisartownhub-file-vault-v1';

    /** AES-GCM standard nonce length (96 bits). */
    private const IV_LENGTH = 12;

    public static function enabled(): bool
    {
        return (bool) config('vault.encryption.enabled', true);
    }

    /** True when the payload carries the encrypted envelope. */
    public static function isEncrypted(string $blob): bool
    {
        return str_starts_with($blob, self::ENVELOPE);
    }

    /** Encrypt plaintext bytes into the vault envelope. */
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipher === false) {
            throw new RuntimeException('Vault encryption failed.');
        }

        return self::ENVELOPE . base64_encode($iv) . "\n" . base64_encode($tag) . "\n" . $cipher;
    }

    /**
     * Decrypt a vault payload. Legacy plaintext (no envelope) is returned
     * unchanged so pre-encryption files keep streaming transparently.
     */
    public static function decrypt(string $blob): string
    {
        if (!self::isEncrypted($blob)) {
            return $blob;
        }

        $parts = explode("\n", substr($blob, strlen(self::ENVELOPE)), 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('Vault payload is malformed.');
        }

        [$iv, $tag, $cipher] = $parts;
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            base64_decode($iv),
            base64_decode($tag)
        );

        if ($plain === false) {
            throw new RuntimeException(
                'Vault decryption failed — the file may have been written with a different APP_KEY.'
            );
        }

        return $plain;
    }

    /** Vault data key derived from APP_KEY (never persisted beside files). */
    private static function key(): string
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required for vault encryption.');
        }

        return hash('sha256', $appKey . '|' . self::KEY_CONTEXT, true);
    }
}
