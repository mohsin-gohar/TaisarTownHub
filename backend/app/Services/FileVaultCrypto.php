<?php

namespace App\\Services;

use RuntimeException;

/**
 * At-rest encryption for the Secure Document & Media Vault.
 *
 * Algorithm: AES-256-GCM (authenticated encryption) via PHP OpenSSL.
 *
 * The active data key is derived from APP_KEY with a vault-specific domain
 * separator. During APP_KEY rotation, keep the old key in Laravel's
 * APP_PREVIOUS_KEYS list and run `php artisan vault:encrypt`. Reads can then
 * decrypt with an old key; the command rewrites those envelopes with the
 * active key. Remove previous keys only after the migration has been
 * verified and a recoverable backup is retained.
 *
 * Envelope format:
 *   "TTHENC1\\n" . base64(iv) . "\\n" . base64(tag) . "\\n" . ciphertext
 *
 * Legacy plaintext (no envelope) is returned unchanged so files written
 * before encryption was enabled keep working without a migration downtime.
 */
class FileVaultCrypto
{
    /** Envelope marker for encrypted vault payloads. */
    public const ENVELOPE = "TTHENC1\\n";

    /** Domain separator so the vault key is distinct from other APP_KEY uses. */
    private const KEY_CONTEXT = 'taisartownhub-file-vault-v1';

    /** AES-GCM standard nonce length (96 bits). */
    private const IV_LENGTH = 12;

    /** AES-GCM authentication tag length (128 bits). */
    private const TAG_LENGTH = 16;

    public static function enabled(): bool
    {
        return (bool) config('vault.encryption.enabled', true);
    }

    /** True when the payload carries the encrypted envelope. */
    public static function isEncrypted(string $blob): bool
    {
        return str_starts_with($blob, self::ENVELOPE);
    }

    /** True only when the envelope authenticates with the currently active APP_KEY. */
    public static function isEncryptedWithCurrentKey(string $blob): bool
    {
        if (!self::isEncrypted($blob)) {
            return false;
        }

        return self::decryptWithKey($blob, self::key()) !== false;
    }

    /** Encrypt plaintext bytes with the currently active APP_KEY. */
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
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipher === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Vault encryption failed.');
        }

        return self::ENVELOPE . base64_encode($iv) . "\\n" . base64_encode($tag) . "\\n" . $cipher;
    }

    /**
     * Decrypt a vault payload using the active key, then configured previous
     * APP_KEY values. Legacy plaintext is returned unchanged.
     */
    public static function decrypt(string $blob): string
    {
        if (!self::isEncrypted($blob)) {
            return $blob;
        }

        foreach (self::keyCandidates() as $key) {
            $plain = self::decryptWithKey($blob, $key);
            if ($plain !== false) {
                return $plain;
            }
        }

        throw new RuntimeException(
            'Vault decryption failed — no configured APP_KEY or APP_PREVIOUS_KEYS entry can authenticate this file.'
        );
    }

    /** @return list<string> */
    private static function keyCandidates(): array
    {
        $keys = [self::key()];
        $previous = config('app.previous_keys', []);

        if (is_array($previous)) {
            foreach ($previous as $appKey) {
                if (!is_string($appKey) || trim($appKey) === '') {
                    continue;
                }
                $keys[] = self::deriveKey(trim($appKey));
            }
        }

        return array_values(array_unique($keys));
    }

    /** Return decrypted bytes, or false when this key cannot authenticate the envelope. */
    private static function decryptWithKey(string $blob, string $key): string|false
    {
        $parts = explode("\\n", substr($blob, strlen(self::ENVELOPE)), 3);
        if (count($parts) !== 3) {
            return false;
        }

        [$ivEncoded, $tagEncoded, $cipher] = $parts;
        $iv = base64_decode($ivEncoded, true);
        $tag = base64_decode($tagEncoded, true);

        if ($iv === false || strlen($iv) !== self::IV_LENGTH
            || $tag === false || strlen($tag) !== self::TAG_LENGTH) {
            return false;
        }

        return openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );
    }

    /** Active vault data key derived from APP_KEY. */
    private static function key(): string
    {
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required for vault encryption.');
        }

        return self::deriveKey($appKey);
    }

    private static function deriveKey(string $appKey): string
    {
        return hash('sha256', $appKey . '|' . self::KEY_CONTEXT, true);
    }
}
