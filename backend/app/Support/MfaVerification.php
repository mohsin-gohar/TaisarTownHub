<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Token-scoped MFA verification state.
 *
 * A verification proved for one Sanctum token must never authorize a
 * different token of the same user. Keys therefore include the access-token
 * id; a per-user index allows bulk invalidation on logout, password reset,
 * suspension and MFA disablement.
 */
final class MfaVerification
{
    public const TTL_SECONDS = 24 * 60 * 60;

    public static function key(int $userId, int $tokenId): string
    {
        return 'mfa_verified:' . $userId . ':' . $tokenId;
    }

    private static function indexKey(int $userId): string
    {
        return 'mfa_verified_ids:' . $userId;
    }

    public static function isVerified(int $userId, int $tokenId): bool
    {
        return Cache::has(self::key($userId, $tokenId));
    }

    public static function stamp(int $userId, int $tokenId): void
    {
        Cache::put(self::key($userId, $tokenId), now(), self::TTL_SECONDS);

        $ids = Cache::get(self::indexKey($userId), []);
        $ids = is_array($ids) ? $ids : [];
        if (!in_array($tokenId, $ids, true)) {
            $ids[] = $tokenId;
        }
        Cache::put(self::indexKey($userId), $ids, self::TTL_SECONDS);
    }

    public static function forgetToken(int $userId, int $tokenId): void
    {
        Cache::forget(self::key($userId, $tokenId));
        $ids = Cache::get(self::indexKey($userId), []);
        if (is_array($ids)) {
            Cache::put(self::indexKey($userId), array_values(array_filter(
                $ids,
                fn ($id) => (int) $id !== $tokenId
            )), self::TTL_SECONDS);
        }
    }

    public static function forgetAll(int $userId): void
    {
        $ids = Cache::get(self::indexKey($userId), []);
        foreach ((array) $ids as $id) {
            Cache::forget(self::key($userId, (int) $id));
        }
        Cache::forget(self::indexKey($userId));
    }
}
