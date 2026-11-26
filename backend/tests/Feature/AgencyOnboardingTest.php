<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\SubscriptionPlan;
use App\Services\AgencyOnboarding;
use Tests\Feature\EnterpriseTestCase;

class AgencyOnboardingTest extends EnterpriseTestCase
{
    private function plan(string $key, bool $active, bool $default, int $sortOrder = 0): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'Onboarding test plan',
            'price' => 0,
            'currency' => 'PKR',
            'interval' => 'month',
            'limits' => ['listings' => 10, 'staff' => 2, 'storage_mb' => 0],
            'features' => [],
            'is_active' => $active,
            'is_default' => $default,
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_starter_subscription_assignment_is_idempotent_per_agency(): void
    {
        $agency = $this->makeAgency('Idempotent Onboarding Agency');
        $default = $this->plan('onboarding-default-' . bin2hex(random_bytes(4)), true, true);

        $first = AgencyOnboarding::ensureStarterSubscription($agency, null);
        $second = AgencyOnboarding::ensureStarterSubscription($agency, null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($default->id, $second->plan_id);
        $this->assertDatabaseCount('agency_subscriptions', 1);
    }

    public function test_inactive_default_plan_is_never_assigned_to_a_new_agency(): void
    {
        $agency = $this->makeAgency('Active Plan Fallback Agency');
        $this->plan('inactive-onboarding-default-' . bin2hex(random_bytes(4)), false, true, 0);
        $activeTrial = $this->plan('active-onboarding-trial-' . bin2hex(random_bytes(4)), true, false, 1);

        $subscription = AgencyOnboarding::ensureStarterSubscription($agency, null);

        $this->assertSame($activeTrial->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }
}
