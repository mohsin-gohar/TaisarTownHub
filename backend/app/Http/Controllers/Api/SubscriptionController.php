<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

/**
 * Agency-facing subscriptions: public plan catalog + the acting agency's own
 * subscription state and usage against plan limits.
 */
class SubscriptionController extends Controller
{
    use AuthorizesApi;

    /** GET /api/plans — public plan catalog. */
    public function plans(Request $request)
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price,
                'currency' => $p->currency,
                'interval' => $p->interval,
                'limits' => $p->limits,
                'features' => $p->features,
                'is_default' => $p->is_default,
            ]);

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }

    /** GET /api/agency/subscription — the acting agency's subscription + usage. */
    public function current(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.view');

        $agency = Agency::findOrFail($agencyId);
        $subscription = AgencySubscription::where('agency_id', $agencyId)->with('plan')->first();

        $limits = $agency->limits();

        $usage = [
            'members' => $agency->activeMembers()->count(),
            'listings' => $agency->properties()->whereNull('deleted_at')->count(),
            'storage_bytes' => (int) \App\Models\Document::where('agency_id', $agencyId)
                ->where('status', 'active')->sum('size_bytes'),
            'leads' => $agency->inquiries()->count(),
        ];

        $remaining = [];
        foreach (['staff', 'listings', 'leads'] as $key) {
            $limit = $limits[$key] ?? null;
            // Usage exposes staff as `members`; map the alias explicitly so
            // the remaining-staff figure is never computed from a missing key.
            $used = $key === 'staff' ? ($usage['members'] ?? 0) : ($usage[$key] ?? 0);
            $remaining[$key] = ($limit === null || (int) $limit <= 0)
                ? null // unlimited
                : max(0, (int) $limit - (int) $used);
        }

        return response()->json([
            'success' => true,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'key' => $subscription->plan->key,
                    'name' => $subscription->plan->name,
                    'price' => $subscription->plan->price,
                    'currency' => $subscription->plan->currency,
                    'interval' => $subscription->plan->interval,
                    'limits' => $subscription->plan->limits,
                    'features' => $subscription->plan->features,
                ] : null,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'auto_renew' => (bool) $subscription->auto_renew,
                'cancelled_at' => $subscription->cancelled_at,
            ] : [
                'status' => 'none',
                'plan' => null,
            ],
            'limits' => $limits,
            'usage' => $usage,
            'remaining' => $remaining,
            'available_plans' => SubscriptionPlan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'key', 'name', 'price', 'currency', 'interval', 'limits', 'features']),
        ]);
    }

    /** GET /api/agency/subscription/invoices — the agency's own billing history. */
    public function invoices(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.view');

        $invoices = SubscriptionInvoice::where('agency_id', $agencyId)
            ->with('plan:id,key,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'invoices' => $invoices->map(fn (SubscriptionInvoice $inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'amount_pkr' => $inv->amount_pkr,
                'tax_pkr' => $inv->tax_pkr,
                'total_pkr' => $inv->total_pkr,
                'currency' => $inv->currency,
                'period_start' => $inv->period_start?->toDateString(),
                'period_end' => $inv->period_end?->toDateString(),
                'status' => $inv->status,
                'paid_at' => $inv->paid_at?->toIso8601String(),
                'payment_method' => $inv->payment_method,
                'plan_name' => $inv->plan?->name,
                'notes' => $inv->notes,
                'created_at' => $inv->created_at?->toIso8601String(),
            ]),
            'totals' => [
                'outstanding_pkr' => (int) $invoices->where('status', 'pending')->sum('total_pkr'),
                'paid_pkr' => (int) $invoices->where('status', 'paid')->sum('total_pkr'),
            ],
        ]);
    }
}
