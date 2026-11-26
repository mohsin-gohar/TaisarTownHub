<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Cache-backed bot protection for the public inquiry form (finding G-11).
 *
 * The browser receives a simple math question and an opaque signed token.
 * The token contains only a random nonce and expiry; the expected answer is
 * stored as an HMAC in the shared cache and is never disclosed to the client.
 *
 * The challenge is single-use across application replicas. Wrong answers do
 * not consume it, while a successful answer is atomically claimed with
 * Cache::add so concurrent replays cannot both succeed.
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
        $answerHash = self::answerHash((string) ($a + $b));

        if (! Cache::put(
            self::challengeKey($nonce),
            $answerHash,
            now()->addSeconds(self::LIFETIME_SECONDS)
        )) {
            throw new RuntimeException('Could not store the inquiry challenge.');
        }

        return [
            'question' => "What is {$a} + {$b}?",
            'token' => self::sign($nonce, $expiresAt),
            'expiresAt' => gmdate('c', $expiresAt),
        ];
    }

    /**
     * Verify a submitted answer against its opaque token. A solved token
     * cannot be replayed; incorrect answers do not consume the challenge.
     */
    public static function verify(?string $token, ?string $answer): bool
    {
        if (! is_string($token) || $token === '' || ! is_string($answer) || $answer === '') {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        [$payload, $signature] = $parts;

        $decoded = base64_decode($payload, true);
        $data = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($data)
            || count($data) !== 2
            || ! isset($data['n'], $data['e'])
            || ! is_string($data['n'])
            || ! preg_match('/^[a-f0-9]{32}$/D', $data['n'])
            || ! is_int($data['e'])
        ) {
            return false;
        }

        // Verify the signature before using the nonce or expiry.
        if (! hash_equals(self::signature($payload), $signature)) {
            return false;
        }

        if ($data['e'] < time()) {
            return false;
        }

        $given = trim($answer);
        if (! ctype_digit($given)) {
            return false;
        }

        $nonce = $data['n'];
        $storedHash = Cache::get(self::challengeKey($nonce));
        if (! is_string($storedHash)
            || ! hash_equals($storedHash, self::answerHash($given))) {
            return false;
        }

        // Only one concurrent request can claim a solved nonce.
        if (! Cache::add(
            self::usedKey($nonce),
            true,
            now()->addMinutes(self::NONCE_TTL_MINUTES)
        )) {
            return false;
        }

        Cache::forget(self::challengeKey($nonce));

        return true;
    }

    private static function sign(string $nonce, int $expiresAt): string
    {
        $payload = base64_encode((string) json_encode([
            'n' => $nonce,
            'e' => $expiresAt,
        ]));

        return $payload . '.' . self::signature($payload);
    }

    private static function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private static function answerHash(string $answer): string
    {
        return hash_hmac('sha256', $answer, (string) config('app.key'));
    }

    private static function challengeKey(string $nonce): string
    {
        return 'inquiry-captcha:challenge:' . $nonce;
    }

    private static function usedKey(string $nonce): string
    {
        return 'inquiry-captcha:used:' . $nonce;
    }
}
