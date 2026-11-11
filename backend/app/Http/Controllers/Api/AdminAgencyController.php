<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Platform agency administration: directory, verification, moderation. */
class AdminAgencyController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_agencies');

        $query = Agency::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $page = $query->withCount(['activeMembers', 'properties'])
            ->latest()
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'agencies' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_agencies');

        $agency = Agency::withCount(['activeMembers', 'properties', 'documents', 'inquiries'])
            ->with(['owner:id,name,email', 'subscription.plan'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'agency' => $agency,
            'members' => $agency->members()->with(['user:id,name,email,status', 'role:id,name,key'])->get(),
        ]);
    }

    /** PATCH /api/admin/agencies/{id}/verify — verified|rejected|unverified */
    public function verify(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_agencies');

        $request->validate([
            'verification_status' => 'required|in:verified,rejected,unverified',
            'notes' => 'nullable|string|max:2000',
        ]);

        $agency = Agency::findOrFail($id);
        $from = $agency->verification_status;

        $agency->verification_status = $request->verification_status;
        $agency->verification_notes = $request->notes;
        $agency->verified_by = $request->user()->id;
        $agency->verified_at = now();

        // Approval also activates the agency
        if ($request->verification_status === 'verified' && $agency->status === 'pending') {
            $agency->status = 'active';
        }

        $agency->save();

        $this->writeAudit($request, 'agency.verify', $agency->id, "Agency {$from} → {$request->verification_status}: {$agency->name}", [
            'agency_id' => $agency->id,
            'entity_type' => 'agency',
            'entity_id' => $agency->id,
            'cross_agency' => true,
            'metadata' => ['from' => $from, 'to' => $request->verification_status, 'notes' => $request->notes],
        ]);

        if ($agency->owner_user_id) {
            $owner = User::find($agency->owner_user_id);
            $owner?->notify(new \App\Notifications\ListingUpdated(
                'Agency ' . $request->verification_status,
                "Your agency \"{$agency->name}\" is now {$request->verification_status}."
                . ($request->notes ? " Note: {$request->notes}" : ''),
                $agency->id
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification status updated.',
            'agency' => $agency,
        ]);
    }

    /** PATCH /api/admin/agencies/{id}/status — active|suspended|rejected */
    public function status(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_agencies');

        $request->validate([
            'status' => 'required|in:active,suspended,rejected,pending',
            'reason' => 'nullable|string|max:1000',
        ]);

        $agency = Agency::findOrFail($id);
        $from = $agency->status;
        $agency->status = $request->status;
        $agency->save();

        $this->writeAudit($request, 'agency.status', $agency->id, "Agency {$from} → {$request->status}: {$agency->name}", [
            'agency_id' => $agency->id,
            'entity_type' => 'agency',
            'entity_id' => $agency->id,
            'cross_agency' => true,
            'metadata' => ['from' => $from, 'to' => $request->status, 'reason' => $request->reason],
        ]);

        Log::info('Agency status changed', [
            'agency_id' => $agency->id, 'from' => $from, 'to' => $request->status,
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agency status updated.',
            'agency' => $agency,
        ]);
    }

    /* -------- Plans & subscriptions -------- */

    public function plans(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    public function storePlan(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $request->validate([
            'key' => 'required|string|max:60|unique:subscription_plans,key',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0|max:99999999',
            'currency' => 'nullable|string|size:3',
            'interval' => 'nullable|in:month,year',
            'limits' => 'nullable|array',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $plan = SubscriptionPlan::create($request->only([
            'key', 'name', 'description', 'price', 'currency', 'interval',
            'limits', 'features', 'is_active', 'is_default', 'sort_order',
        ]));

        $this->writeAudit($request, 'plan.create', $plan->key, "Plan created: {$plan->name}", [
            'entity_type' => 'subscription_plan',
            'entity_id' => (string) $plan->id,
            'cross_agency' => true,
        ]);

        return response()->json(['success' => true, 'plan' => $plan], 201);
    }

    public function updatePlan(Request $request, int $id)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $plan = SubscriptionPlan::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|numeric|min:0|max:99999999',
            'interval' => 'sometimes|in:month,year',
            'limits' => 'nullable|array',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
        ]);

        $plan->fill($request->only([
            'name', 'description', 'price', 'interval', 'limits', 'features',
            'is_active', 'is_default', 'sort_order',
        ]));

        if ($request->boolean('is_default')) {
            SubscriptionPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);
        }
        $plan->save();

        $this->writeAudit($request, 'plan.update', $plan->key, "Plan updated: {$plan->name}", [
            'entity_type' => 'subscription_plan',
            'entity_id' => (string) $plan->id,
            'cross_agency' => true,
        ]);

        return response()->json(['success' => true, 'plan' => $plan]);
    }

    /** PUT /api/admin/agencies/{id}/subscription — assign/override a plan. */
    public function assignSubscription(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'status' => 'nullable|in:trialing,active,expired,cancelled',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'limits_override' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        $agency = Agency::findOrFail($id);

        $subscription = AgencySubscription::updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'plan_id' => $request->plan_id,
                'status' => $request->get('status', 'active'),
                'starts_at' => $request->starts_at ?: now(),
                'ends_at' => $request->ends_at,
                'trial_ends_at' => $request->trial_ends_at,
                'limits_override' => $request->limits_override,
                'notes' => $request->notes,
                'created_by' => $request->user()->id,
            ]
        );

        $this->writeAudit($request, 'subscription.assign', $agency->id, "Subscription assigned to {$agency->name}", [
            'agency_id' => $agency->id,
            'entity_type' => 'agency_subscription',
            'entity_id' => (string) $subscription->id,
            'cross_agency' => true,
            'metadata' => ['plan_id' => $request->plan_id, 'status' => $subscription->status],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated.',
            'subscription' => $subscription->load('plan'),
        ]);
    }
}
