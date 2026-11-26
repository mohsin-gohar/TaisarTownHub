<?php

namespace App\Services;

use RuntimeException;

/**
 * At-rest encryption for the Secure Document & Media Vault.
 *
 * AES-256-GCM with a vault-specific key derived from APP_KEY. Laravel's
 * APP_PREVIOUS_KEYS are also accepted for decryption during key rotation.
 * New writes always use the current APP_KEY.
 *
 * Rotation procedure:
 *  1. Set the new APP_KEY and put the old APP_KEY in APP_PREVIOUS_KEYS.
 *  2. Deploy the same key pair to every app/worker/scheduler replica.
 *  3. Run php artisan vault:encrypt and require zero errors.
 *  4. Verify the vault and encrypted model fields, then remove the old key
 *     from APP_PREVIOUS_KEYS in a later controlled deployment.
 *
 * Envelope: "TTHENC1\n" . base64(iv) . "\n" . base64(tag) . "\n" . ciphertext
 * Legacy plaintext remains readable and is migrated by vault:encrypt.
 */
class FileVaultCrypto
{
    public const ENVELOPE = "TTHENC1\n";
    private const KEY_CONTEXT = 'taisartownhub-file-vault-v1';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public static function enabled(): bool
    {
        return (bool) config('vault.encryption.enabled', true);
    }

    public static function isEncrypted(string $blob): bool
    {
        return str_starts_with($blob, self::ENVELOPE);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            self::keyFor((string) config('app.key')),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipher === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Vault encryption failed.');
        }

        return self::ENVELOPE . base64_encode($iv) . "\n" . base64_encode($tag) . "\n" . $cipher;
    }

    /**
     * Decrypt with the current key first, then Laravel APP_PREVIOUS_KEYS.
     * This allows a controlled APP_KEY rotation without losing access to
     * encrypted files. Re-encryption is performed by vault:encrypt.
     */
    public static function decrypt(string $blob): string
    {
        if (!self::isEncrypted($blob)) {
            return $blob;
        }

        [$iv, $tag, $cipher] = self::parts($blob);
        $keys = array_values(array_unique(array_filter(
            array_map(
                static fn ($key): string => trim((string) $key),
                array_merge(
                    [(string) config('app.key')],
                    (array) config('app.previous_keys', [])
                )
            ),
            static fn (string $key): bool => $key !== ''
        )));

        foreach ($keys as $appKey) {
            $plain = self::decryptWithAppKey($cipher, $iv, $tag, $appKey);
            if ($plain !== false) {
                return $plain;
            }
        }

        throw new RuntimeException(
            'Vault decryption failed — the file was written with a key that is not in APP_KEY or APP_PREVIOUS_KEYS.'
        );
    }

    /** Whether an encrypted payload can be authenticated with the current APP_KEY. */
    public static function usesCurrentKey(string $blob): bool
    {
        if (!self::isEncrypted($blob)) {
            return false;
        }

        $appKey = (string) config('app.key');
        if ($appKey === '') {
            return false;
        }

        try {
            [$iv, $tag, $cipher] = self::parts($blob);

            return self::decryptWithAppKey($cipher, $iv, $tag, $appKey) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{string,string,string} */
    private static function parts(string $blob): array
    {
        $parts = explode("\n", substr($blob, strlen(self::ENVELOPE)), 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('Vault payload is malformed.');
        }

        [$encodedIv, $encodedTag, $cipher] = $parts;
        $iv = base64_decode($encodedIv, true);
        $tag = base64_decode($encodedTag, true);

        if (!is_string($iv) || strlen($iv) !== self::IV_LENGTH
            || !is_string($tag) || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Vault payload has an invalid IV or authentication tag.');
        }

        return [$iv, $tag, $cipher];
    }

    private static function decryptWithAppKey(string $cipher, string $iv, string $tag, string $appKey): string|false
    {
        return openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::keyFor($appKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

    private static function keyFor(string $appKey): string
    {
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required for vault encryption.');
        }

        return hash('sha256', $appKey . '|' . self::KEY_CONTEXT, true);
    }
}
