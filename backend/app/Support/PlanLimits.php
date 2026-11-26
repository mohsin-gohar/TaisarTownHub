<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DocumentQuarantine;
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
        // version, and the media studio files. In this release the total is
        // used for monitoring and reporting only — it never blocks an upload.
        $documentBytes = (int) Document::withTrashed()
            ->where('agency_id', $agency->id)
            ->sum('size_bytes');

        // The current version is already represented by documents.size_bytes.
        // Only archived versions add additional retained bytes; counting every
        // DocumentVersion row double-counts the current version after each upload.
        $versionBytes = (int) DocumentVersion::query()
            ->join('documents as vault_documents', 'vault_documents.id', '=', 'document_versions.document_id')
            ->where('vault_documents.agency_id', $agency->id)
            ->whereColumn('document_versions.version', '<', 'vault_documents.version')
            ->sum('document_versions.size_bytes');

        $mediaBytes = (int) MediaFile::withTrashed()
            ->where('agency_id', $agency->id)
            ->sum('size_bytes');
        $quarantineBytes = (int) DocumentQuarantine::where('agency_id', $agency->id)->sum('size_bytes');

        return [
            'listings' => $agency->properties()->whereNull('deleted_at')->count(),
            'members' => $agency->activeMembers()->count(),
            'storage_bytes' => $documentBytes + $versionBytes + $mediaBytes + $quarantineBytes,
            'leads' => $agency->inquiries()->count(),
            'featured_credits' => $agency->properties()
                ->whereNull('deleted_at')
                ->where('approval_status', 'approved')
                ->where('isFeatured', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
        ];
    }

    /**
     * Featured credits are concurrent featured-listing slots, not an unlimited
     * counter: zero means no slots, while null explicitly means unlimited.
     * This differs from the generic listing/staff convention where zero means
     * unlimited, so it has its own guard.
     *
     * @return array{limit:int,used:int}|null
     */
    public static function featuredSlotBlock(Agency $agency): ?array
    {
        $limit = $agency->limits()['featured_credits'] ?? 0;
        if ($limit === null) {
            return null;
        }

        $limit = max(0, (int) $limit);
        $used = self::usage($agency)['featured_credits'];

        return $used + 1 > $limit
            ? ['limit' => $limit, 'used' => $used]
            : null;
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

    /**
     * Commercial gate: an agency may only record sales (dealer files, media)
     * while its subscription is genuinely active. Agencies without a
     * subscription row keep working (legacy/default plans), but a
     * past-due/cancelled or time-expired subscription blocks the sell paths
     * even when the agency account itself is still marked active.
     */
    public static function subscriptionActive(Agency $agency): bool
    {
        $subscription = $agency->subscription;

        if ($subscription === null) {
            return true; // no subscription row — legacy/default access
        }

        if ($subscription->status !== 'active') {
            return false; // past_due, cancelled, ...
        }

        if ($subscription->ends_at !== null && $subscription->ends_at->isPast()) {
            return false; // paid term ended
        }

        return true;
    }

    /** Standardised 403 response for subscription-blocked commerce actions. */
    public static function subscriptionDenied(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'subscription_inactive',
            'message' => 'Your agency subscription is inactive or expired. Renew your plan to record sales.',
        ], 403);
    }

    /** Staff counter for the invite flow: active members + pending invitations. */
    public static function staffUsed(string $agencyId): int
    {
        return AgencyMember::where('agency_id', $agencyId)->where('status', 'active')->count()
            + AgencyInvitation::where('agency_id', $agencyId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count();
    }
}

