<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionPlan;

/**
 * Safe starter-workspace assignment.
 *
 * A newly registered agency must have a real quota row so the database — not
 * the browser — decides how much data it may store. This service only creates
 * a missing subscription; it never downgrades or overwrites an existing plan.
 *
 * The public UI does not advertise a free/trial period. Trial metadata is kept
 * only for future billing decisions and can never trigger file deletion.
 */
class AgencyOnboarding
{
    public const DEFAULT_STORAGE_MB = 5120; // 5 GB
    public const DEFAULT_TRIAL_DAYS = 30;

    public static function ensureStarterSubscription(Agency $agency, ?int $createdBy = null): AgencySubscription
    {
        $existing = AgencySubscription::where('agency_id', $agency->id)->first();
        if ($existing) {
            return $existing;
        }

        $plan = SubscriptionPlan::where('is_default', true)->first()
            ?: SubscriptionPlan::where('key', 'trial')->first()
            ?: SubscriptionPlan::orderBy('sort_order')->first();

        if (!$plan) {
            // No catalogue yet (e.g. a very early test database). A
            // subscription row requires a real plan because `plan_id` is
            // NOT NULL, so create the free starter plan rather than returning
            // an unsaved model that would fail on first persist.
            $plan = SubscriptionPlan::create([
                'key' => 'starter-workspace',
                'name' => 'Starter Workspace',
                'description' => 'Default agency workspace with a 5 GB storage quota.',
                'price' => 0,
                'currency' => 'PKR',
                'interval' => 'month',
                'limits' => [
                    'listings' => 10,
                    'staff' => 2,
                    'storage_mb' => self::DEFAULT_STORAGE_MB,
                    'featured_credits' => 0,
                    'listing_days' => 30,
                ],
                'features' => [],
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);
        }

        $limits = $plan->limits ?? [];
        $limits['storage_mb'] = max(self::DEFAULT_STORAGE_MB, (int) ($limits['storage_mb'] ?? 0));

        return AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(self::DEFAULT_TRIAL_DAYS),
            'limits_override' => ['storage_mb' => $limits['storage_mb']],
            'created_by' => $createdBy,
        ]);
    }
}
