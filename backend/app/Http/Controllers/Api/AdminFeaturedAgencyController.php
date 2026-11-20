<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyPromotion;
use App\Models\PromotionPackage;
use App\Models\SystemSetting;
use App\Services\AgencyPromotionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin management of homepage featured-agency placements.
 *
 * Placement is an advertising entitlement, separate from agency approval and
 * from listing moderation: only approved + verified + active agencies may be
 * placed, and every change is audited with the acting platform user.
 */
class AdminFeaturedAgencyController extends Controller
{
    use AuthorizesApi;

    public function __construct(private readonly AgencyPromotionService $promotions)
    {
    }

    /** GET /api/admin/featured-agencies */
    public function index(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $query = AgencyPromotion::query()
            ->with([
                'agency:id,name,slug,logo_url,city,status,verification_status,office_address',
                'package:id,name,key,duration_days,price,currency',
            ])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderBy('position')
            ->orderByDesc('created_at');

        if ($request->filled('status') && in_array($request->status, AgencyPromotion::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status') && in_array($request->payment_status, AgencyPromotion::PAYMENT_STATUSES, true)) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }
        if ($request->filled('search')) {
            $search = addcslashes((string) $request->search, '%_\\');
            $query->whereHas('agency', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $placements = $query->limit(300)->get();

        // Agencies that can be placed right now (approved, verified, active,
        // and without a current placement).
        $eligibleAgencies = Agency::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->whereDoesntHave('promotions', fn ($q) => $q->current())
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'slug', 'city', 'logo_url']);

        return response()->json([
            'success' => true,
            'placements' => $placements,
            'packages' => PromotionPackage::orderBy('sort_order')->orderBy('duration_days')->get(),
            'settings' => $this->promotions->settings(),
            'stats' => $this->promotions->stats(),
            'eligible_agencies' => $eligibleAgencies,
        ]);
    }

    /** POST /api/admin/featured-agencies */
    public function store(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $data = $request->validate([
            'agency_id' => 'required|string|max:64|exists:agencies,id',
            'package_id' => 'nullable|integer|exists:promotion_packages,id',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'position' => 'nullable|integer|min:1|max:100',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'currency' => 'nullable|string|size:3',
            'payment_status' => ['nullable', Rule::in(AgencyPromotion::PAYMENT_STATUSES)],
            'payment_method' => 'nullable|string|max:40',
            'payment_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'status' => ['nullable', Rule::in(['active', 'pending'])],
        ]);

        $agency = Agency::findOrFail($data['agency_id']);

        try {
            $promotion = $this->promotions->promote($agency, $data, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->writeAudit(
            $request,
            'featured_agency.create',
            (string) $promotion->id,
            "Featured placement created for {$agency->name} (position {$promotion->position}, {$promotion->payment_status})",
            [
                'agency_id' => $agency->id,
                'entity_type' => 'agency_promotion',
                'entity_id' => (string) $promotion->id,
                'cross_agency' => true,
                'metadata' => [
                    'position' => $promotion->position,
                    'starts_at' => optional($promotion->starts_at)->toIso8601String(),
                    'ends_at' => optional($promotion->ends_at)->toIso8601String(),
                    'price' => (float) $promotion->price,
                    'payment_status' => $promotion->payment_status,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Featured placement created.',
            'placement' => $promotion,
        ], 201);
    }

    /** PUT /api/admin/featured-agencies/{id} */
    public function update(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $promotion = AgencyPromotion::with('agency')->findOrFail($id);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(AgencyPromotion::STATUSES)],
            'position' => 'nullable|integer|min:1|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'price' => 'sometimes|numeric|min:0|max:99999999',
            'currency' => 'sometimes|string|size:3',
            'payment_status' => ['sometimes', Rule::in(AgencyPromotion::PAYMENT_STATUSES)],
            'payment_method' => 'nullable|string|max:40',
            'payment_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $promotion = $this->promotions->update($promotion, $data, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->writeAudit(
            $request,
            'featured_agency.update',
            (string) $promotion->id,
            "Featured placement updated for {$promotion->agency?->name} ({$promotion->status})",
            [
                'agency_id' => $promotion->agency_id,
                'entity_type' => 'agency_promotion',
                'entity_id' => (string) $promotion->id,
                'cross_agency' => true,
                'metadata' => [
                    'status' => $promotion->status,
                    'payment_status' => $promotion->payment_status,
                    'position' => $promotion->position,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Featured placement updated.',
            'placement' => $promotion,
        ]);
    }

    /** POST /api/admin/featured-agencies/{id}/cancel */
    public function cancel(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $promotion = AgencyPromotion::with('agency')->findOrFail($id);

        try {
            $promotion = $this->promotions->cancel($promotion, $request->user(), $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->writeAudit(
            $request,
            'featured_agency.cancel',
            (string) $promotion->id,
            "Featured placement cancelled for {$promotion->agency?->name}",
            [
                'agency_id' => $promotion->agency_id,
                'entity_type' => 'agency_promotion',
                'entity_id' => (string) $promotion->id,
                'cross_agency' => true,
                'metadata' => ['reason' => $data['reason'] ?? null],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Featured placement cancelled. Agency listings and files are untouched.',
            'placement' => $promotion,
        ]);
    }

    /** PUT /api/admin/featured-agencies/order */
    public function reorder(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|integer|distinct',
        ]);

        try {
            $this->promotions->reorder($data['ids'], $request->user());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->writeAudit(
            $request,
            'featured_agency.reorder',
            'featured-roster',
            'Homepage featured-agency order updated',
            [
                'entity_type' => 'agency_promotion',
                'cross_agency' => true,
                'metadata' => ['ids' => $data['ids']],
            ]
        );

        return response()->json(['success' => true, 'message' => 'Featured order updated.']);
    }

    /** POST /api/admin/featured-agencies/packages */
    public function storePackage(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $data = $request->validate([
            'key' => 'required|string|max:60|unique:promotion_packages,key',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'currency' => 'nullable|string|size:3',
            'duration_days' => 'required|integer|min:1|max:3650',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $package = PromotionPackage::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? 0,
            'currency' => $data['currency'] ?? 'PKR',
            'duration_days' => $data['duration_days'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->writeAudit($request, 'featured_package.create', $package->key, "Featured package created: {$package->name}", [
            'entity_type' => 'promotion_package',
            'entity_id' => (string) $package->id,
            'cross_agency' => true,
        ]);

        return response()->json(['success' => true, 'package' => $package], 201);
    }

    /** PUT /api/admin/featured-agencies/packages/{id} */
    public function updatePackage(Request $request, int $id)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $package = PromotionPackage::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|numeric|min:0|max:99999999',
            'currency' => 'sometimes|string|size:3',
            'duration_days' => 'sometimes|integer|min:1|max:3650',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
        ]);

        $package->fill($data)->save();

        $this->writeAudit($request, 'featured_package.update', $package->key, "Featured package updated: {$package->name}", [
            'entity_type' => 'promotion_package',
            'entity_id' => (string) $package->id,
            'cross_agency' => true,
        ]);

        return response()->json(['success' => true, 'package' => $package]);
    }

    /** PUT /api/admin/featured-agencies/settings */
    public function updateSettings(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_promotions');

        $data = $request->validate([
            'max_slots' => 'sometimes|integer|min:1|max:' . AgencyPromotionService::ABSOLUTE_MAX_SLOTS,
            'default_duration_days' => 'sometimes|integer|min:1|max:3650',
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::set('promotions.' . $key, (int) $value, $request->user()->id);
        }

        $this->writeAudit(
            $request,
            'featured_agency.settings',
            'promotions',
            'Featured-agency settings updated',
            [
                'entity_type' => 'system_setting',
                'cross_agency' => true,
                'metadata' => $data,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Featured-agency settings updated.',
            'settings' => $this->promotions->settings(),
        ]);
    }
}
