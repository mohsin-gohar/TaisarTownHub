<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Activates the agency subscription represented by a paid invoice.
 *
 * Call from the same database transaction that marks the invoice paid. The
 * service also locks the agency row so concurrent provider/admin callbacks
 * cannot create overlapping subscription state.
 */
class SubscriptionActivationService
{
    public function activate(SubscriptionInvoice $invoice): ?AgencySubscription
    {
        if (!$invoice->plan_id) {
            return null;
        }

        $agency = Agency::query()->whereKey($invoice->agency_id)->lockForUpdate()->firstOrFail();
        $plan = SubscriptionPlan::query()->whereKey($invoice->plan_id)->firstOrFail();

        if (strtoupper((string) $invoice->currency) !== 'PKR' || strtoupper((string) $plan->currency) !== 'PKR') {
            throw new RuntimeException('Only PKR subscription invoices can activate an agency plan.');
        }

        $provider = $invoice->stripe_subscription_id
            ? 'stripe'
            : ($invoice->payment_method === 'flutterwave' ? 'flutterwave' : 'manual');

        $current = AgencySubscription::query()
            ->where('agency_id', $agency->id)
            ->lockForUpdate()
            ->first();

        if (
            $current
            && $current->stripe_subscription_id
            && $current->stripe_subscription_id !== $invoice->stripe_subscription_id
            && in_array($current->status, ['active', 'past_due'], true)
            && $current->ends_at
            && $current->ends_at->isFuture()
        ) {
            throw new RuntimeException('An active Stripe subscription must be cancelled before switching billing providers.');
        }

        $startsAt = $invoice->period_start
            ? $invoice->period_start->copy()->startOfDay()
            : ($invoice->paid_at ?? now());

        $endsAt = $invoice->period_end
            ? $invoice->period_end->copy()->endOfDay()
            : ($plan->interval === 'year'
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth());

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new RuntimeException('The subscription invoice has an invalid billing period.');
        }

        return AgencySubscription::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'auto_renew' => $provider === 'stripe',
                'stripe_subscription_id' => $invoice->stripe_subscription_id,
                'provider' => $provider,
                'cancelled_at' => null,
                'updated_at' => now(),
            ]
        );
    }
}
