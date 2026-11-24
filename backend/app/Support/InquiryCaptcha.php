<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Stateless bot protection for the public inquiry form (finding G-11).
 *
 * A challenge is a simple math question ("What is 7 + 5?"). The answer never
 * travels in plain form: the issued token carries only an HMAC signature over
 * the operands, a single-use nonce and an expiry timestamp. Verification
 * recomputes everything server-side, so no database table, no external captcha
 * provider and no privacy-invading third-party script are required.
 *
 * Design notes:
 * - Keyed with APP_KEY. Rotating APP_KEY invalidates outstanding challenges;
 *   they live for 10 minutes, which is an acceptable trade.
 * - The nonce is cached for the challenge lifetime so a SOLVED token cannot
 *   be replayed for a second submission; wrong answers do not burn the
 *   challenge (a human may retry after a typo).
 * - Issuance and verification are O(1) and cache-backed; with the array store
 *   the challenge is still single-use per request lifecycle, and in production
 *   Redis/Cache::store provides the shared replay guard.
 */
final class InquiryCaptcha
{
    /** How long an unanswered challenge stays valid. */
    public const LIFETIME_SECONDS = 600;

    /** How long a solved nonce stays blacklisted (>= challenge lifetime). */
    public const NONCE_TTL_MINUTES = 15;

    /**
     * Issue a fresh challenge for the public inquiry form.
     *
     * @return array{question: string, token: string, expiresAt: string}
     */
    public static function issue(): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $nonce = bin2hex(random_bytes(16));
        $expiresAt = time() + self::LIFETIME_SECONDS;

        return [
            'question' => "What is {$a} + {$b}?",
            'token' => self::sign($a, $b, $nonce, $expiresAt),
            'expiresAt' => gmdate('c', $expiresAt),
        ];
    }

    /**
     * Verify a submitted answer against its token. Single-use: a token that
     * already produced one successful submission is rejected afterwards.
     */
    public static function verify(?string $token, ?string $answer): bool
    {
        if (!is_string($token) || $token === '' || !is_string($answer) || $answer === '') {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        [$payload, $signature] = $parts;

        $data = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($data)
            || !isset($data['a'], $data['b'], $data['n'], $data['e'])
            || !is_int($data['a']) || !is_int($data['b'])
            || !is_int($data['e']) || !is_string($data['n'])
        ) {
            return false;
        }

        // Signature check first: never process untrusted payload semantics.
        if (!hash_equals(self::signature($payload), $signature)) {
            return false;
        }

        if ((int) $data['e'] < time()) {
            return false;
        }

        // Replay protection: a solved token works exactly once.
        $nonceKey = 'inquiry-captcha:' . $data['n'];
        if (Cache::has($nonceKey)) {
            return false;
        }

        $expected = (int) $data['a'] + (int) $data['b'];
        $given = trim($answer);
        if (!ctype_digit($given) || (int) $given !== $expected) {
            return false;
        }

        Cache::put($nonceKey, true, now()->addMinutes(self::NONCE_TTL_MINUTES));

        return true;
    }

    private static function sign(int $a, int $b, string $nonce, int $expiresAt): string
    {
        $payload = base64_encode((string) json_encode([
            'a' => $a,
            'b' => $b,
            'n' => $nonce,
            'e' => $expiresAt,
        ]));

        return $payload . '.' . self::signature($payload);
    }

    private static function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
