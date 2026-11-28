<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

/**
 * Safe starter-workspace assignment.
 *
 * A newly registered agency receives a real subscription row so the database
 * decides the active plan and its non-storage limits. Storage is not quota-
 * enforced in this release; bytes are monitored for reporting only and no file
 * is ever deleted because of them.
 */
class AgencyOnboarding
{
    public const DEFAULT_STORAGE_MB = 0; // No storage trial/quota in this release
    public const DEFAULT_TRIAL_DAYS = 0;

    public static function ensureStarterSubscription(Agency $agency, ?int $createdBy = null): AgencySubscription
    {
        // Serialize concurrent onboarding for the same agency: lock the agency
        // row so a check-then-create cannot race and insert duplicate
        // subscription rows (one subscription per agency).
        return DB::transaction(function () use ($agency, $createdBy) {
            Agency::whereKey($agency->id)->lockForUpdate()->first();

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
                    'description' => 'Default agency workspace (storage unlimited in this release).',
                    'price' => 0,
                    'currency' => 'PKR',
                    'interval' => 'month',
                    'limits' => [
                        'listings' => 10,
                        'staff' => 2,
                        'storage_mb' => 0,
                        'featured_credits' => 0,
                        'listing_days' => 30,
                    ],
                    'features' => [],
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => 0,
                ]);
            }

            // Storage is unlimited for this launch; no trial or quota is assigned.
            // Future billing can reintroduce limits here if needed.
            return AgencySubscription::create([
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'trial_ends_at' => null,
                'created_by' => $createdBy,
            ]);
        });
    }
}
