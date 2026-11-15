<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Real TOTP (RFC 6238, HMAC-SHA1 / RFC 4226) multi-factor authentication.
 *
 * Implemented in-house on top of PHP's hash_hmac so the project gains no new
 * runtime dependency. Secrets and recovery codes are stored encrypted (model
 * casts) — the plaintext only ever exists in the enrolment response or in the
 * one-time recovery-code list shown to the account owner.
 */
class MfaService
{
    public const PERIOD = 30;      // seconds per time step
    public const DIGITS = 6;       // code length
    public const WINDOW = 1;       // accept one step of clock drift each way
    public const RECOVERY_CODE_COUNT = 8;

    /** 160-bit secret, base32 encoded (as authenticator apps expect). */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** otpauth:// provisioning URI for QR-code enrolment. */
    public function otpauthUri(string $email, string $secret): string
    {
        $label = rawurlencode('TaisarTownHub:' . $email);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => 'TaisarTownHub',
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /** Code for the current time step (used by tests and by the verifier). */
    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeAt($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    /** Constant-time comparison against the allowed drift window. */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code) ?? '';

        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Human-friendly single-use recovery codes (shown exactly once). */
    public function generateRecoveryCodes(int $count = self::RECOVERY_CODE_COUNT): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5) . '-' . Str::random(5));
        }

        return $codes;
    }

    /** Recovery codes are stored hashed, exactly like passwords. */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => Hash::make($code), $codes);
    }

    /**
     * Single-use consumption of a recovery code.
     * Returns true when the code matched; the used code is removed from the
     * account in the same call so it can never be replayed.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $stored = is_array($user->mfa_recovery_codes) ? $user->mfa_recovery_codes : [];
        $code = strtoupper(trim((string) $code));

        if ($code === '' || $stored === []) {
            return false;
        }

        foreach ($stored as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($stored[$index]);
                $user->forceFill(['mfa_recovery_codes' => array_values($stored)])->save();

                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim(trim($secret), '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
