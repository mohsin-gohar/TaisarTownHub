<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Platform agency administration: directory, verification, moderation. */
class AdminAgencyController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_agencies');

        $query = Agency::query();

        if ($request->status === 'verified') {
            $query->where('verification_status', 'verified')->where('status', 'active');
        } elseif ($request->status === 'rejected') {
            $query->where(fn ($q) => $q->where('status', 'rejected')->orWhere('verification_status', 'rejected'));
        } elseif ($request->status === 'pending') {
            $query->where('status', 'pending')->where('verification_status', '!=', 'rejected');
        } elseif ($request->filled('status')) {
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
            ->orderByDesc('created_at')
            ->orderBy('id')
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

        $data = $request->validate([
            'verification_status' => 'required|in:verified,rejected,unverified',
            'notes' => 'nullable|string|max:2000',
        ]);

        [$agency, $from] = DB::transaction(function () use ($request, $id, $data): array {
            $agency = Agency::whereKey($id)->lockForUpdate()->firstOrFail();
            $from = $agency->verification_status;

            $agency->verification_status = $data['verification_status'];
            $agency->verification_notes = $data['notes'] ?? null;
            $agency->verified_by = $request->user()->id;
            $agency->verified_at = $data['verification_status'] === 'verified' ? now() : null;

            // Approval activates a pending agency, but never silently revives
            // an agency separately suspended/rejected by platform moderation.
            if ($data['verification_status'] === 'verified' && $agency->status === 'pending') {
                $agency->status = 'active';
            }

            $agency->save();

            if ($data['verification_status'] === 'verified') {
                \App\Services\AgencyOnboarding::ensureStarterSubscription($agency, $request->user()->id);
            }

            return [$agency->fresh(), $from];
        }, 3);

        $this->writeAudit($request, 'agency.verify', $agency->id, "Agency {$from} → {$data['verification_status']}: {$agency->name}", [
            'agency_id' => $agency->id,
            'entity_type' => 'agency',
            'entity_id' => $agency->id,
            'cross_agency' => true,
            'metadata' => ['from' => $from, 'to' => $data['verification_status'], 'notes' => $data['notes'] ?? null],
        ]);

        if ($agency->owner_user_id) {
            try {
                $owner = User::find($agency->owner_user_id);
                $owner?->notify(new \App\Notifications\ListingUpdated(
                    'Agency ' . $data['verification_status'],
                    "Your agency \"{$agency->name}\" is now {$data['verification_status']}."
                        . (!empty($data['notes']) ? " Note: {$data['notes']}" : ''),
                    $agency->id
                ));
            } catch (\Throwable $exception) {
                Log::warning('Agency verification notification could not be queued', [
                    'agency_id' => $agency->id,
                    'error' => $exception->getMessage(),
                ]);
            }
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
            'price' => 'required|integer|min:0|max:99999999',
            'currency' => 'nullable|in:PKR',
            'interval' => 'nullable|in:month,year',
            'limits' => 'nullable|array',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $planData = $request->only([
            'key', 'name', 'description', 'price', 'interval',
            'limits', 'features', 'is_active', 'is_default', 'sort_order',
        ]);
        $planData['currency'] = 'PKR';

        if (($planData['is_default'] ?? false) && array_key_exists('is_active', $planData) && !$planData['is_active']) {
            return response()->json(['success' => false, 'message' => 'An inactive plan cannot be the default plan.'], 422);
        }

        $plan = DB::transaction(function () use ($planData): SubscriptionPlan {
            if ($planData['is_default'] ?? false) {
                $this->lockDefaultPlanMutex();
                SubscriptionPlan::where('is_default', true)->update(['is_default' => false]);
            }

            return SubscriptionPlan::create($planData);
        }, 3);

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

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|integer|min:0|max:99999999',
            'currency' => 'sometimes|in:PKR',
            'interval' => 'sometimes|in:month,year',
            'limits' => 'nullable|array',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
        ]);

        $fields = $request->only([
            'name', 'description', 'price', 'currency', 'interval', 'limits', 'features',
            'is_active', 'is_default', 'sort_order',
        ]);

        if ($request->boolean('is_default') && array_key_exists('is_active', $fields) && !$fields['is_active']) {
            return response()->json(['success' => false, 'message' => 'An inactive plan cannot be the default plan.'], 422);
        }

        $plan = DB::transaction(function () use ($request, $id, $fields): SubscriptionPlan {
            $plan = SubscriptionPlan::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($request->boolean('is_default')) {
                if (array_key_exists('is_active', $fields) ? !$fields['is_active'] : !$plan->is_active) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'is_default' => ['An inactive plan cannot be the default plan.'],
                    ]);
                }

                $this->lockDefaultPlanMutex();
                SubscriptionPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);
            }

            $plan->fill($fields);
            $plan->save();

            return $plan->fresh();
        }, 3);

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

        $data = $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'status' => 'nullable|in:trialing,active,expired,cancelled',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'limits_override' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        [$agency, $subscription] = DB::transaction(function () use ($request, $id, $data): array {
            $agency = Agency::whereKey($id)->lockForUpdate()->firstOrFail();
            $plan = SubscriptionPlan::whereKey($data['plan_id'])->lockForUpdate()->firstOrFail();
            if (!$plan->is_active) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'plan_id' => ['The selected subscription plan is inactive.'],
                ]);
            }

            $subscription = AgencySubscription::updateOrCreate(
                ['agency_id' => $agency->id],
                [
                    'plan_id' => $plan->id,
                    'status' => $data['status'] ?? 'active',
                    'starts_at' => $data['starts_at'] ?? now(),
                    'ends_at' => $data['ends_at'] ?? null,
                    'trial_ends_at' => $data['trial_ends_at'] ?? null,
                    'limits_override' => $data['limits_override'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]
            );

            return [$agency, $subscription];
        }, 3);

        $this->writeAudit($request, 'subscription.assign', $agency->id, "Subscription assigned to {$agency->name}", [
            'agency_id' => $agency->id,
            'entity_type' => 'agency_subscription',
            'entity_id' => (string) $subscription->id,
            'cross_agency' => true,
            'metadata' => ['plan_id' => $data['plan_id'], 'status' => $subscription->status],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated.',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /* ==================== Subscription invoices (G-7) ==================== */

    /** GET /api/admin/subscription-invoices — all invoices, filterable. */
    public function invoicesIndex(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $query = SubscriptionInvoice::query()->with(['agency:id,name', 'plan:id,key,name,price']);

        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }
        if ($request->filled('status') && in_array($request->status, SubscriptionInvoice::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(fn ($q) => $q->where('invoice_number', 'like', "%{$s}%"));
        }

        $invoices = $query->orderByDesc('created_at')->limit(200)->get();

        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'totals' => [
                'amount_pkr' => (int) $invoices->sum('amount_pkr'),
                'outstanding_pkr' => (int) $invoices->where('status', 'pending')->sum('total_pkr'),
            ],
        ]);
    }

    /** POST /api/admin/subscription-invoices — raise an invoice (starts pending). */
    public function invoicesStore(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $data = $request->validate([
            'agency_id' => 'required|string|max:64|exists:agencies,id',
            'plan_id' => 'nullable|integer|exists:subscription_plans,id',
            'amount_pkr' => 'required|integer|min:0|max:9999999999',
            'tax_pkr' => 'nullable|integer|min:0|max:999999999',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:2000',
        ]);

        $agency = Agency::findOrFail($data['agency_id']);

        $invoice = SubscriptionInvoice::create([
            'id' => 'inv_' . bin2hex(random_bytes(8)),
            'invoice_number' => SubscriptionInvoice::nextNumber(),
            'agency_id' => $agency->id,
            'plan_id' => $data['plan_id'] ?? null,
            'amount_pkr' => (int) $data['amount_pkr'],
            'tax_pkr' => (int) ($data['tax_pkr'] ?? 0),
            'total_pkr' => (int) $data['amount_pkr'] + (int) ($data['tax_pkr'] ?? 0),
            'currency' => 'PKR',
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->writeAudit($request, 'subscription.invoice_created', $invoice->invoice_number,
            "Invoice {$invoice->invoice_number} raised for {$agency->name} (PKR {$invoice->total_pkr})", [
                'agency_id' => $agency->id,
                'entity_type' => 'subscription_invoice',
                'entity_id' => $invoice->id,
                'cross_agency' => true,
                'metadata' => ['total_pkr' => $invoice->total_pkr, 'status' => 'pending'],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created.',
            'invoice' => $invoice->load('agency:id,name'),
        ], 201);
    }

    /**
     * POST /api/admin/subscription-invoices/{id}/verify — mark a PENDING
     * invoice as paid (payment details + verifier recorded). A paid invoice
     * is financial history and can never be modified again.
     */
    public function invoiceVerify(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_subscriptions');

        $data = $request->validate([
            'payment_method' => 'required|string|max:40',
            'payment_reference' => 'nullable|string|max:120',
        ]);

        $result = DB::transaction(function () use ($request, $id, $data): array {
            $invoice = SubscriptionInvoice::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'paid') {
                return ['error' => 'paid'];
            }
            if ($invoice->status === 'void') {
                return ['error' => 'void'];
            }

            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'verified_by' => $request->user()->id,
            ])->save();

            return ['invoice' => $invoice->fresh(['agency:id,name', 'verifier:id,name'])];
        }, 3);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] === 'paid'
                    ? 'This invoice is already paid — financial history cannot be modified.'
                    : 'A voided invoice cannot be paid.',
            ], 422);
        }

        $invoice = $result['invoice'];
        $this->writeAudit($request, 'subscription.invoice_paid', $invoice->invoice_number,
            "Invoice {$invoice->invoice_number} marked paid (PKR {$invoice->total_pkr})", [
                'agency_id' => $invoice->agency_id,
                'entity_type' => 'subscription_invoice',
                'entity_id' => $invoice->id,
                'cross_agency' => true,
                'metadata' => ['payment_method' => $data['payment_method']],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid.',
            'invoice' => $invoice,
        ]);
    }


    /** Serialize default-plan changes even when the plan catalogue is empty. */
    private function lockDefaultPlanMutex(): void
    {
        SystemSetting::query()->firstOrCreate(
            ['key' => 'subscriptions.default_plan_lock'],
            [
                'value' => 1,
                'group' => 'subscriptions',
                'description' => 'Serializes default subscription plan changes.',
            ]
        );

        SystemSetting::query()
            ->where('key', 'subscriptions.default_plan_lock')
            ->lockForUpdate()
            ->firstOrFail();
    }


}
