<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MfaVerification
{
    private const TTL_MINUTES = 1440;

    private static function indexKey(int|string $userId): string
    {
        return "mfa_verified_tokens:{$userId}";
    }

    private static function lockKey(int|string $userId): string
    {
        return "mfa_verified_tokens_lock:{$userId}";
    }

    private static function tokenKey(int|string $userId, string $tokenId): string
    {
        return "mfa_verified:{$userId}:{$tokenId}";
    }

    public static function stamp(int|string $userId, string $tokenId): void
    {
        Cache::lock(self::lockKey($userId), 10)->block(5, function () use ($userId, $tokenId): void {
            Cache::put(self::tokenKey($userId, $tokenId), true, now()->addMinutes(self::TTL_MINUTES));

            $index = Cache::get(self::indexKey($userId), []);
            if (!is_array($index)) {
                $index = [];
            }
            $index[] = $tokenId;

            Cache::put(
                self::indexKey($userId),
                array_values(array_unique($index)),
                now()->addMinutes(self::TTL_MINUTES)
            );
        });
    }

    public static function isVerified(int|string $userId, string $tokenId): bool
    {
        return Cache::get(self::tokenKey($userId, $tokenId), false) === true;
    }

    public static function forgetToken(int|string $userId, string $tokenId): void
    {
        Cache::lock(self::lockKey($userId), 10)->block(5, function () use ($userId, $tokenId): void {
            Cache::forget(self::tokenKey($userId, $tokenId));

            $index = Cache::get(self::indexKey($userId), []);
            if (is_array($index)) {
                $index = array_values(array_filter(
                    $index,
                    static fn ($id): bool => (string) $id !== $tokenId
                ));
                if ($index === []) {
                    Cache::forget(self::indexKey($userId));
                } else {
                    Cache::put(self::indexKey($userId), $index, now()->addMinutes(self::TTL_MINUTES));
                }
            }
        });
    }

    public static function forgetAll(int|string $userId): void
    {
        Cache::lock(self::lockKey($userId), 10)->block(5, function () use ($userId): void {
            $index = Cache::get(self::indexKey($userId), []);
            if (is_array($index)) {
                foreach ($index as $tokenId) {
                    Cache::forget(self::tokenKey($userId, (string) $tokenId));
                }
            }
            Cache::forget(self::indexKey($userId));
        });
    }
}
