<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;

/**
 * G-8 — server-side plan-limit enforcement.
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
        // Storage accounting must cover EVERY byte the agency keeps on the
        // private disks: live + trashed vault documents (trashed bytes are
        // retained for audit until permanent delete), every archived
        // version, and the media studio files. Counting only `active`
        // documents let an agency bypass the quota via trash or media.
        $documentBytes = (int) Document::withTrashed()
            ->where('agency_id', $agency->id)
            ->sum('size_bytes');

        $versionBytes = (int) DocumentVersion::query()
            ->whereIn('document_id', Document::withTrashed()->where('agency_id', $agency->id)->select('id'))
            ->sum('size_bytes');

        $mediaBytes = (int) MediaFile::withTrashed()
            ->where('agency_id', $agency->id)
            ->sum('size_bytes');

        return [
            'listings' => $agency->properties()->whereNull('deleted_at')->count(),
            'members' => $agency->activeMembers()->count(),
            'storage_bytes' => $documentBytes + $versionBytes + $mediaBytes,
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

