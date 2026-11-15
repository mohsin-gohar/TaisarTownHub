<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

/**
 * G-8 â€” server-side plan-limit enforcement.
 *
 * Usage counters mirror SubscriptionController::current exactly (staff is
 * displayed as `members`), so the API can never accept an action the usage
 * panel claims is impossible. Convention: a limit of null or <= 0 means
 * UNLIMITED (same rule the remaining-usage display uses).
 *
 * Every violation returns 402 with code `plan_limit_reached` so the frontend
 * can distinguish quota failures from validation errors.
 */
class PlanLimits
{
    /** @return array<string, int> current usage for the agency. */
    public static function usage(Agency $agency): array
    {
        return [
            'listings' => $agency->properties()->whereNull('deleted_at')->count(),
            'members' => $agency->activeMembers()->count(),
            'storage_bytes' => (int) Document::where('agency_id', $agency->id)
                ->where('status', 'active')->sum('size_bytes'),
            'leads' => $agency->inquiries()->count(),
        ];
    }

    /**
     * Count-limit guard. Returns null when the action is allowed, or
     * ['limit' => int, 'used' => int] when it would exceed the plan.
     *
     * @param callable(): int|null $usedFn custom counter (e.g. staff = members + pending invites)
     */
    public static function block(Agency $agency, string $limitKey, ?callable $usedFn = null): ?array
    {
        $limit = $agency->limits()[$limitKey] ?? null;

        if ($limit === null || (int) $limit <= 0) {
            return null; // unlimited
        }

        $used = $usedFn ? (int) $usedFn() : (self::usage($agency)[$limitKey] ?? 0);

        if ($used + 1 > (int) $limit) {
            return ['limit' => (int) $limit, 'used' => $used];
        }

        return null;
    }

    /** Byte-based guard for vault storage (`storage_mb` limit). */
    public static function storageBlock(Agency $agency, int $incomingBytes): ?array
    {
        $limitMb = $agency->limits()['storage_mb'] ?? null;

        if ($limitMb === null || (int) $limitMb <= 0) {
            return null; // unlimited
        }

        $used = self::usage($agency)['storage_bytes'];
        $limitBytes = (int) $limitMb * 1048576;

        if ($used + $incomingBytes > $limitBytes) {
            return ['limit' => (int) $limitMb, 'used' => $used];
        }

        return null;
    }

    /** Standardised 402 response for every limit violation. */
    public static function deny(string $message, array $block): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'plan_limit_reached',
            'message' => $message,
            'limit' => $block['limit'],
            'used' => $block['used'],
        ], 402);
    }

    /** Staff counter for the invite flow: active members + pending invitations. */
    public static function staffUsed(string $agencyId): int
    {
        return AgencyMember::where('agency_id', $agencyId)->count()
            + AgencyInvitation::where('agency_id', $agencyId)->where('status', 'pending')->count();
    }
}

