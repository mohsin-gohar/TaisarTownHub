<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Safe starter-workspace assignment.
 *
 * A newly registered agency receives one real subscription row so the
 * database decides the active plan and its non-storage limits. Storage is not
 * quota-enforced in this release; bytes are monitored for reporting only and
 * no file is ever deleted because of them.
 */
class AgencyOnboarding
{
    public const DEFAULT_STORAGE_MB = 0; // No storage trial/quota in this release
    public const DEFAULT_TRIAL_DAYS = 0;

    public static function ensureStarterSubscription(Agency $agency, ?int $createdBy = null): AgencySubscription
    {
        return DB::transaction(function () use ($agency, $createdBy): AgencySubscription {
            // The agency row is the per-tenant mutex. It serializes duplicate
            // onboarding calls even when no subscription row exists yet.
            $lockedAgency = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();

            $existing = AgencySubscription::where('agency_id', $lockedAgency->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $plan = SubscriptionPlan::where('is_active', true)
                ->where('is_default', true)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->first()
                ?: SubscriptionPlan::where('is_active', true)
                    ->where('key', 'trial')
                    ->lockForUpdate()
                    ->first()
                ?: SubscriptionPlan::where('is_active', true)
                    ->orderBy('sort_order')
                    ->lockForUpdate()
                    ->first();

            if (!$plan) {
                // No active catalogue yet (e.g. a fresh test database). Create
                // one deterministic free plan; the unique key makes concurrent
                // first-workspace creation safe across different agencies.
                $plan = SubscriptionPlan::firstOrCreate(
                    ['key' => 'starter-workspace'],
                    [
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
                    ]
                );

                if (!$plan->is_active) {
                    throw new RuntimeException('No active subscription plan is available for agency onboarding.');
                }
            }

            // Unique agency_id remains the final database-level duplicate guard.
            return AgencySubscription::firstOrCreate(
                ['agency_id' => $lockedAgency->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'trial_ends_at' => null,
                    'created_by' => $createdBy,
                ]
            );
        }, 3);
    }
}
