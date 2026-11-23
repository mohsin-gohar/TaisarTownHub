<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyPromotion;
use App\Models\BusinessRecord;
use App\Models\Dealer;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Models\PropertyView;
use App\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    use AuthorizesApi;

    /**
     * GET /api/properties — listings.
     *
     * - Guests: only approved + public + unexpired listings.
     * - Agency context (X-Agency-Id / membership, requires properties.view):
     *   that agency's own listings in every state (`scope=agency` default when
     *   a context is resolved).
     * - Platform moderator (properties.approve) with `scope=all`: everything,
     *   filterable by approval_status (moderation queue).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $agencyId = $this->agencyId($request);
        $scope = $request->get('scope');

        $query = Property::query();

        $isModerator = $user && $this->can($request, 'properties.approve');
        $canSeeOwn = $user && $agencyId && $this->can($request, 'properties.view');
        $isPublicScope = !$user || $scope === 'public' || (!$agencyId && $scope !== 'all' && $scope !== 'moderation');

        if (($scope === 'all' || $scope === 'moderation') && $isModerator) {
            // platform-wide view (moderation / admin)
            if ($request->filled('approval_status')) {
                $query->where('approval_status', $request->approval_status);
            }
            if ($request->filled('agency_id')) {
                $query->where('agency_id', $request->agency_id);
            }
        } elseif ($scope === 'agency' || ($agencyId && $scope !== 'public')) {
            if (!$canSeeOwn) {
                abort(403, 'Permission denied.');
            }
            $query->forAgency($agencyId);
            if ($request->filled('approval_status')) {
                $query->where('approval_status', $request->approval_status);
            } else {
                $query->where(function ($q) {
                    $q->where('approval_status', '!=', 'archived');
                });
            }
        } else {
            $query->publiclyVisible();
            $query->where('listing_purpose', 'sale');
        }

        // Public callers can never opt into rental listings. Internal/legacy
        // rental records remain preserved for explicit internal workflows.
        if ($isPublicScope) {
            $request->merge(['purpose' => 'sale', 'listing_purpose' => 'sale']);
            $query->where('listing_purpose', 'sale');
        }

        if ($request->filled('agency_id') && $scope === 'public') {
            $query->where('agency_id', $request->agency_id);
        }

        $purpose = $request->get('purpose') ?? $request->get('listing_purpose');
        if ($purpose === 'buy') {
            $purpose = 'sale';
        }
        if (in_array($purpose, ['sale', 'rent'], true)) {
            $query->where('listing_purpose', $purpose);
        }

        if ($request->filled('phase') && $request->phase !== 'All') {
            $query->where('phase', $request->phase);
        }
        if ($request->filled('type') && $request->type !== 'All') {
            $query->where('type', $request->type);
        }
        if ($request->filled('dealerId')) {
            $query->where('assignedDealerId', $request->dealerId);
        }
        if ($request->filled('sector') && $request->sector !== 'All') {
            $query->where('sector', $request->sector);
        }
        if ($request->filled('featured')) {
            $query->featured();
        }
        if ($request->filled('search')) {
            $s = $this->escapeLike((string) $request->search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('plotNumber', 'like', "%{$s}%")
                    ->orWhere('sector', 'like', "%{$s}%")
                    ->orWhere('fileNumber', 'like', "%{$s}%");
            });
        }

        // Promoted agencies get priority placement for their approved public
        // listings. The badge is truthful (Featured vs Sponsored) and the
        // ordering falls back to recency for everyone else.
        if ($isPublicScope) {
            $now = now();
            // Scalar subquery: promoted listings sort by their live slot
            // position (1 = first), everyone else falls back to the sentinel
            // below and keeps recency order. Agency status is re-checked so a
            // later suspension removes the priority immediately.
            $promotionPrioritySql = 'COALESCE((SELECT MIN(ap.position)
                FROM agency_promotions ap
                INNER JOIN agencies ag ON ag.id = ap.agency_id
                WHERE ap.agency_id = properties.agency_id
                  AND ap.status = ?
                  AND (ap.starts_at IS NULL OR ap.starts_at <= ?)
                  AND (ap.ends_at IS NULL OR ap.ends_at > ?)
                  AND ag.status = ?
                  AND ag.verification_status = ?), 1000000)';
            $query->orderByRaw(
                $promotionPrioritySql,
                ['active', $now, $now, 'active', 'verified']
            );
            $query->with(['agency:id,name,slug,status,verification_status', 'agency.activePromotion']);
        }

        // Deterministic ordering: `created_at` only has second precision, so a
        // stable id tiebreaker is required — otherwise two listings created in
        // the same second can swap places between pages.
        $query->orderByDesc('created_at')->orderBy('id');

        // Pagination envelope only when explicitly requested — otherwise the
        // legacy raw-array contract is preserved (capped for safety).
        if ($request->has('page')) {
            $perPage = min((int) $request->get('per_page', 20), 100);
            $page = $query->paginate($perPage);

            if ($isPublicScope) {
                $page->through(fn (Property $p) => $this->publicPropertyPayload($p));
            } elseif ($request->boolean('with_meta')) {
                $canSeeSensitive = $this->can($request, 'view_sensitive_cnic');
                $page->through(fn (Property $p) => $this->serializeForScope($p, $user, $agencyId, $canSeeSensitive));
            }

            return response()->json([
                'success' => true,
                'properties' => $page->items(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        }

        $results = $query->limit(min((int) $request->get('per_page', 100), 500))->get();

        if ($isPublicScope) {
            $results->transform(fn (Property $p) => $this->publicPropertyPayload($p));
        } elseif ($request->boolean('with_meta')) {
            $canSeeSensitive = $this->can($request, 'view_sensitive_cnic');
            $results->transform(fn (Property $p) => $this->serializeForScope($p, $user, $agencyId, $canSeeSensitive));
        }

        return response()->json($results);
    }

    /**
     * GET /api/properties/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $agencyId = $this->agencyId($request);

        $property = Property::whereNull('deleted_at')->findOrFail($id);

        $isOwnerAgency = $agencyId !== null && $property->agency_id === $agencyId;
        $isModerator = $user && $this->can($request, 'properties.approve');

        if ($isOwnerAgency
            && $user
            && !$user->hasPermission('properties.view', $agencyId)
        ) {
            $ownerAgency = Agency::find($agencyId);
            if ($ownerAgency && $ownerAgency->status !== 'active') {
                abort(404, 'Property not found.');
            }
            abort(403, 'Permission denied.');
        }

        if (!$property->isPubliclyVisible()
            || $property->listing_purpose !== 'sale'
        ) {
            if (!$isOwnerAgency && !$isModerator) {
                abort(404, 'Property not found.');
            }
        }

        if (!$property->isPubliclyVisible() && !$isOwnerAgency && !$isModerator) {
            abort(404, 'Property not found.');
        }

        // Public view tracking (deduped per user/IP per property per day)
        if (!$user || !$isOwnerAgency) {
            $this->recordView($property, $user);
        }

        if ($isOwnerAgency || $isModerator) {
            $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes']);
            $property->makeVisible(['disk_path'] ?? []);
        } elseif ($user && $this->can($request, 'view_sensitive_cnic')) {
            $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes']);
        } else {
            return response()->json($this->publicPropertyPayload($property));
        }

        return response()->json($property);
    }

    /**
     * POST /api/properties — create a listing (server-generated id, agency bound).
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['properties.create', 'create_properties']);

        $agencyId = $this->requireAgencyForWrite($request);

        // G-8: plan limit — listings quota is enforced server-side.
        $agency = Agency::find($agencyId);
        if ($agency && ($block = PlanLimits::block($agency, 'listings'))) {
            return PlanLimits::deny('Listing limit reached for your plan. Upgrade to create more listings.', $block);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'pricePKR' => 'required|numeric|min:0',
            'phase' => 'required|string|max:100',
            'sector' => 'required|string|max:100',
            'sizeSqYards' => 'required|numeric|min:0',
            'plotNumber' => 'nullable|string|max:50',
            'fileNumber' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:50',
            'roadWidthFeet' => 'nullable|numeric|min:0',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|max:50',
            'listing_purpose' => 'nullable|in:sale',
            'isFeatured' => 'nullable|boolean',
            'possessionReady' => 'nullable|boolean',
            'mdaApproved' => 'nullable|boolean',
            'westOpen' => 'nullable|boolean',
            'cornerPlot' => 'nullable|boolean',
            'parkFacing' => 'nullable|boolean',
            'mainBoulevard' => 'nullable|boolean',
            'images' => 'nullable|array|max:30',
            'images.*' => 'string|max:2048',
            'features' => 'nullable|array|max:40',
            'videoUrl' => 'nullable|string|max:1024',
            'bedrooms' => 'nullable|integer|min:0|max:50',
            'bathrooms' => 'nullable|integer|min:0|max:50',
            'contactPerson' => 'nullable|string|max:255',
            'contactPhone' => 'nullable|string|max:30',
            'contactWhatsApp' => 'nullable|string|max:30',
            'streetNumber' => 'nullable|string|max:50',
            'frontFeet' => 'nullable|integer|min:0',
            'depthFeet' => 'nullable|integer|min:0',
            'assignedDealerId' => 'nullable|string|max:64',
            'purchasePricePKR' => 'nullable|numeric|min:0',
            'sellingPricePKR' => 'nullable|numeric|min:0',
            'internalNotes' => 'nullable|string|max:5000',
            'expires_at' => 'nullable|date',
            'submit' => 'nullable|boolean',
        ]);

        if (!empty($data['assignedDealerId'])) {
            $this->assertDealerInAgency($data['assignedDealerId'], $agencyId);
        }

        $propertyId = 'prop_' . bin2hex(random_bytes(8));
        $now = now();

        $payload = collect($data)->except(['submit', 'id'])->all();
        $payload['id'] = $propertyId;
        $payload['agency_id'] = $agencyId;
        $payload['created_by'] = $request->user()->id;
        $payload['approval_status'] = $request->boolean('submit') ? 'pending' : 'draft';
        $payload['visibility'] = 'private';
        $payload['createdDate'] = $now->toDateString();
        $payload['submitted_at'] = $request->boolean('submit') ? $now : null;
        $payload['slug'] = $this->uniqueSlug($data['title'], $propertyId);
        $payload['listing_purpose'] = 'sale';

        $property = Property::create($payload);

        if ($request->boolean('submit')) {
            $this->recordTransition($property, null, 'pending', 'Submitted for approval');
        } else {
            $this->recordTransition($property, null, 'draft', 'Created as draft');
        }

        $this->mirrorToLegacyStore($property, 'create');
        $this->writeAudit($request, 'property.create', $property->id, "Property created: {$property->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'metadata' => ['approval_status' => $property->approval_status],
        ]);

        return response()->json($property, 201);
    }

    /**
     * PUT/PATCH /api/properties/{id}
     *
     * Edits to public-facing (REAPPROVAL_FIELDS) content on an APPROVED
     * listing are staged in `pending_changes` — the live public copy is not
     * altered until a moderator approves them.
     */
    public function update(Request $request, $id)
    {
        $this->requireAny($request, ['properties.update', 'edit_properties']);

        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $agencyId = $this->agencyId($request);

        $this->assertCanManage($request, $property, $agencyId);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'pricePKR' => 'sometimes|numeric|min:0',
            'phase' => 'sometimes|string|max:100',
            'sector' => 'sometimes|string|max:100',
            'sizeSqYards' => 'sometimes|numeric|min:0',
            'plotNumber' => 'nullable|string|max:50',
            'fileNumber' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:50',
            'roadWidthFeet' => 'nullable|numeric|min:0',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|max:50',
            'listing_purpose' => 'sometimes|in:sale',
            'isFeatured' => 'nullable|boolean',
            'possessionReady' => 'nullable|boolean',
            'mdaApproved' => 'nullable|boolean',
            'westOpen' => 'nullable|boolean',
            'cornerPlot' => 'nullable|boolean',
            'parkFacing' => 'nullable|boolean',
            'mainBoulevard' => 'nullable|boolean',
            'images' => 'nullable|array|max:30',
            'images.*' => 'string|max:2048',
            'features' => 'nullable|array|max:40',
            'videoUrl' => 'nullable|string|max:1024',
            'bedrooms' => 'nullable|integer|min:0|max:50',
            'bathrooms' => 'nullable|integer|min:0|max:50',
            'contactPerson' => 'nullable|string|max:255',
            'contactPhone' => 'nullable|string|max:30',
            'contactWhatsApp' => 'nullable|string|max:30',
            'streetNumber' => 'nullable|string|max:50',
            'frontFeet' => 'nullable|integer|min:0',
            'depthFeet' => 'nullable|integer|min:0',
            'assignedDealerId' => 'nullable|string|max:64',
            'purchasePricePKR' => 'nullable|numeric|min:0',
            'sellingPricePKR' => 'nullable|numeric|min:0',
            'internalNotes' => 'nullable|string|max:5000',
            'expires_at' => 'nullable|date',
        ]);

        unset($data['id'], $data['agency_id'], $data['created_by']);

        if (array_key_exists('assignedDealerId', $data) && $data['assignedDealerId'] !== null) {
            $this->assertDealerInAgency($data['assignedDealerId'], $agencyId);
        }

        $isModerator = $this->can($request, 'properties.approve');
        $needsReapproval = $property->approval_status === 'approved' && !$isModerator;

        $immediate = [];
        $staged = [];

        foreach ($data as $key => $value) {
            if ($needsReapproval && in_array($key, Property::REAPPROVAL_FIELDS, true)) {
                $current = $property->{$key};
                $equal = is_array($value) && is_array($current)
                    ? $value == $current
                    : (string) ($value ?? '') === (string) ($current ?? '');
                if (!$equal) {
                    $staged[$key] = $value;
                }
            } else {
                $immediate[$key] = $value;
            }
        }

        if ($immediate) {
            $property->fill($immediate);
        }

        if ($staged) {
            $pending = array_merge($property->pending_changes ?? [], $staged);
            $property->pending_changes = $pending;
            $property->submitted_at = now();
            if ($property->approval_status === 'approved') {
                $property->approval_status = 'pending'; // pending edits, live copy untouched
            }
        }

        $property->updated_by = $request->user()->id;
        $property->save();

        if ($staged) {
            $this->recordTransition(
                $property,
                'approved',
                'pending',
                'Public-facing content edited — re-approval required'
            );
        }

        $this->mirrorToLegacyStore($property, 'update');
        $this->writeAudit($request, 'property.update', $property->id, "Property updated: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'metadata' => [
                'immediate' => array_keys($immediate),
                'staged_for_reapproval' => array_keys($staged),
            ],
        ]);

        $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes']);

        return response()->json($property);
    }

    /**
     * DELETE /api/properties/{id} — soft delete.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAny($request, ['properties.delete', 'delete_properties']);

        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $agencyId = $this->agencyId($request);
        $this->assertCanManage($request, $property, $agencyId);

        $property->deletedBy = (string) $request->user()->id;
        $property->save();
        $property->delete();

        $this->mirrorToLegacyStore($property, 'delete');
        $this->writeAudit($request, 'property.delete', $property->id, "Property soft-deleted: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
        ]);

        return response()->json(['message' => 'Property deleted successfully']);
    }

    /* ================================================================ */
    /* Approval workflow                                                 */
    /* ================================================================ */

    /** POST /api/properties/{id}/submit — draft|rejected → pending */
    public function submit(Request $request, $id)
    {
        $this->requireAny($request, ['properties.publish', 'create_properties', 'edit_properties']);

        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $agencyId = $this->agencyId($request);
        $this->assertCanManage($request, $property, $agencyId);

        if (!in_array($property->approval_status, ['draft', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or rejected listings can be submitted.',
            ], 422);
        }

        $property->approval_status = 'pending';
        $property->submitted_at = now();
        $property->rejection_reason = null;
        $property->save();

        $this->recordTransition($property, 'draft', 'pending', $request->get('reason'));
        $this->mirrorToLegacyStore($property, 'update');
        $this->writeAudit($request, 'property.submit', $property->id, "Submitted for approval: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Listing submitted for approval.', 'property' => $property]);
    }

    /** GET /api/moderation/queue — pending listings (platform moderation). */
    public function moderationQueue(Request $request)
    {
        $this->requirePermission($request, 'properties.approve');

        $query = Property::query()->where('approval_status', 'pending')->whereNull('deleted_at');

        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        $page = $query->with('agency:id,name')
            ->orderBy('submitted_at')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        $page->through(function (Property $p) {
            $payload = $p->toArray();
            $payload['agency_name'] = $p->agency?->name;
            $payload['pending_changes'] = $p->pending_changes;
            $payload['creator_name'] = $p->creator?->name;
            return $payload;
        });

        return response()->json([
            'success' => true,
            'queue' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** POST /api/properties/{id}/approve — approve listing (+ pending edits). */
    public function approve(Request $request, $id)
    {
        $this->requirePermission($request, 'properties.approve');

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'expiry_days' => 'nullable|integer|min:1|max:730',
        ]);

        $property = Property::whereNull('deleted_at')->findOrFail($id);

        if ($property->approval_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending listings can be approved.',
            ], 422);
        }

        $from = $property->approval_status;
        $hadPendingEdits = !empty($property->pending_changes);

        // Apply staged edits to the live public copy
        if ($hadPendingEdits) {
            $property->fill($property->pending_changes);
            $property->pending_changes = null;
        }

        $property->approval_status = 'approved';
        $property->rejection_reason = null;
        $property->visibility = 'public';
        if ($property->published_at === null) {
            $property->published_at = now();
        }
        $days = (int) ($request->expiry_days
            ?? \App\Models\SystemSetting::get('listing.default_expiry_days', 90));
        $property->expires_at = now()->addDays($days);
        $property->save();

        $this->recordTransition($property, $from, 'approved', $request->note);
        $this->mirrorToLegacyStore($property, 'update');
        $this->writeAudit($request, 'property.approve', $property->id, "Approved: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'metadata' => ['applied_pending_edits' => $hadPendingEdits, 'note' => $request->note],
        ]);

        $this->notifyAgency($property->agency_id, 'Listing approved', "{$property->title} is now live on the marketplace.", $property->id);

        return response()->json(['success' => true, 'message' => 'Listing approved and published.', 'property' => $property]);
    }

    /** POST /api/properties/{id}/reject */
    public function reject(Request $request, $id)
    {
        $this->requirePermission($request, 'properties.approve');

        $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        $property = Property::whereNull('deleted_at')->findOrFail($id);

        if (!in_array($property->approval_status, ['pending'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending listings can be rejected.',
            ], 422);
        }

        $from = $property->approval_status;
        $property->approval_status = 'rejected';
        $property->rejection_reason = $request->reason;
        // Staged edits stay for the agency to review; live copy unchanged.
        if ($property->visibility === 'public' && !$property->isPubliclyVisible()) {
            // listing was already live and this was a re-approval reject →
            // revert to previously approved content: staged edits discarded.
            $property->pending_changes = null;
            $property->visibility = 'public';
            $property->approval_status = 'approved';
            $property->save();

            $this->recordTransition($property, $from, 'approved', 'Rejected re-approval edit: ' . $request->reason);
            $this->writeAudit($request, 'property.reject', $property->id, "Re-approval rejected (live copy kept): {$property->title}", [
                'agency_id' => $property->agency_id,
                'entity_type' => 'property',
                'entity_id' => $property->id,
                'metadata' => ['reason' => $request->reason],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Edit rejected — the previously approved listing stays live.',
                'property' => $property,
            ]);
        }

        $property->visibility = 'private';
        $property->save();

        $this->recordTransition($property, $from, 'rejected', $request->reason);
        $this->mirrorToLegacyStore($property, 'update');
        $this->writeAudit($request, 'property.reject', $property->id, "Rejected: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'metadata' => ['reason' => $request->reason],
        ]);

        $this->notifyAgency($property->agency_id, 'Listing needs changes', "{$property->title}: {$request->reason}", $property->id);

        return response()->json(['success' => true, 'message' => 'Listing rejected.', 'property' => $property]);
    }

    /**
     * POST /api/properties/{id}/visibility — agency toggles marketplace visibility.
     * Never bypasses approval: only approved listings can be made public.
     */
    public function setMarketplaceVisibility(Request $request, $id)
    {
        $this->requireAny($request, ['properties.update', 'properties.publish', 'edit_properties']);

        $request->validate([
            'visibility' => 'required|in:public,private',
        ]);

        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $agencyId = $this->agencyId($request);
        $this->assertCanManage($request, $property, $agencyId);

        $target = $request->input('visibility');

        if ($target === 'public') {
            if ($property->approval_status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved listings can be published to the marketplace.',
                ], 422);
            }
            if ($property->expires_at && $property->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This listing has expired and cannot be published.',
                ], 422);
            }
            $property->visibility = 'public';
            if ($property->published_at === null) {
                $property->published_at = now();
            }
        } else {
            $property->visibility = 'private';
        }

        $property->updated_by = $request->user()->id;
        $property->save();

        $this->mirrorToLegacyStore($property, 'update');
        $this->writeAudit($request, 'property.visibility', $property->id, "Property visibility set to {$target}: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'metadata' => ['visibility' => $target, 'approval_status' => $property->approval_status],
        ]);

        $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes']);

        return response()->json([
            'success' => true,
            'message' => $target === 'public' ? 'Listing is now visible on your public profile.' : 'Listing is now hidden from the public marketplace.',
            'property' => $property,
        ]);
    }

    /** POST /api/properties/{id}/feature — agency requests, platform grants. */
    public function feature(Request $request, $id)
    {
        $property = Property::whereNull('deleted_at')->findOrFail($id);
        $agencyId = $this->agencyId($request);
        $user = $request->user();

        $isModerator = $this->can($request, 'properties.approve')
            || $user->hasPermission('properties.feature', null) && $user->isPlatformUser();

        if ($isModerator && $request->has('status')) {
            $request->validate(['status' => 'in:granted,denied']);
            $property->featured_status = $request->status;
            if ($request->status === 'granted') {
                $property->isFeatured = true;
            } else {
                // Denied: the listing must never remain publicly featured.
                $property->isFeatured = false;
            }
            $property->save();

            $this->writeAudit($request, 'property.feature.' . $request->status, $property->id, "Featured {$request->status}: {$property->title}", [
                'agency_id' => $property->agency_id,
                'entity_type' => 'property',
                'entity_id' => $property->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Featured status ' . $request->status . '.', 'property' => $property]);
        }

        // Agency side: request featuring
        $this->requireAny($request, ['properties.feature']);
        $this->assertCanManage($request, $property, $agencyId);

        $property->featured_status = 'requested';
        $property->save();

        $this->writeAudit($request, 'property.feature.request', $property->id, "Featured requested: {$property->title}", [
            'agency_id' => $property->agency_id,
            'entity_type' => 'property',
            'entity_id' => $property->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Featured listing requested — pending platform review.', 'property' => $property]);
    }

    /* ================================================================ */
    /* Helpers                                                           */
    /* ================================================================ */

    /** Agency members must have an agency context; platform admins may act without one. */
    private function requireAgencyForWrite(Request $request): ?string
    {
        $agencyId = $this->agencyId($request);
        $user = $request->user();

        if ($agencyId !== null) {
            return $agencyId;
        }

        // Platform-level creator (no membership): allowed only for platform admins.
        if ($user && $user->isPlatformUser() && $user->hasPermission('admin.manage_agencies')) {
            return null;
        }

        abort(403, 'No agency context for this action.');
    }

    /** Record-level ownership: agency users only touch their own listings. */
    private function assertCanManage(Request $request, Property $property, ?string $agencyId): void
    {
        $user = $request->user();

        if ($agencyId !== null) {
            if ($property->agency_id !== $agencyId) {
                // Cross-agency access must not leak existence
                abort(404, 'Property not found.');
            }
            return;
        }

        if ($user->isPlatformUser() && $user->hasPermission('admin.manage_agencies')) {
            return; // platform admin, audited as cross-agency
        }

        abort(404, 'Property not found.');
    }

    private function assertDealerInAgency(string $dealerId, ?string $agencyId): void
    {
        $dealer = Dealer::where('id', $dealerId)->first();
        if (!$dealer) {
            abort(422, 'Unknown dealer.');
        }
        if ($agencyId !== null && $dealer->agency_id !== null && $dealer->agency_id !== $agencyId) {
            abort(422, 'Dealer belongs to another agency.');
        }
        if ($agencyId !== null && $dealer->agency_id === null) {
            abort(422, 'Dealer is not linked to your agency.');
        }
    }

    private function recordTransition(Property $property, ?string $from, string $to, ?string $reason): void
    {
        try {
            PropertyStatusHistory::create([
                'property_id' => $property->id,
                'agency_id' => $property->agency_id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'actor_user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Status history write failed', ['error' => $e->getMessage()]);
        }
    }

    private function recordView(Property $property, $user): void
    {
        try {
            $today = now()->toDateString();
            $ipHash = hash('sha256', ($user ? 'u:' . $user->id : 'ip:' . request()->ip()) . '|' . $property->id . '|' . $today);

            $exists = PropertyView::where('property_id', $property->id)
                ->where('ip_hash', $ipHash)
                ->whereDate('created_at', $today)
                ->exists();

            if (!$exists) {
                PropertyView::create([
                    'property_id' => $property->id,
                    'user_id' => $user?->id,
                    'ip_hash' => $ipHash,
                    'referrer' => substr((string) request()->headers->get('referer'), 0, 255) ?: null,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('View tracking failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyAgency(?string $agencyId, string $title, string $body, string $entityId): void
    {
        if (!$agencyId) {
            return;
        }
        try {
            $members = \App\Models\AgencyMember::where('agency_id', $agencyId)
                ->where('status', 'active')
                ->pluck('user_id');

            foreach ($members as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $user->notify(new \App\Notifications\ListingUpdated($title, $body, $entityId));
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notification write failed', ['error' => $e->getMessage()]);
        }
    }

    /** Mirror the relational row into the legacy business_records store. */
    private function mirrorToLegacyStore(Property $property, string $mode): void
    {
        try {
            if ($mode === 'delete') {
                DB::table('business_records')
                    ->where('collection', 'properties')
                    ->where('record_id', $property->id)
                    ->update(['deleted_at' => now()]);
                return;
            }

            $payload = $property->toArray();
            unset($payload['deleted_at'], $payload['pending_changes']);
            $payload['deletedAt'] = null;

            BusinessRecord::put('properties', $property->id, $payload);
            DB::table('business_records')
                ->where('collection', 'properties')
                ->where('record_id', $property->id)
                ->update(['agency_id' => $property->agency_id, 'deleted_at' => null]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Legacy property mirror failed', ['error' => $e->getMessage()]);
        }
    }

    private function serializeForScope(Property $property, $user, ?string $agencyId, bool $canSeeSensitive = false): array
    {
        $payload = $property->toArray();
        // Confidential deal fields require the explicit sensitive-data grant.
        // Agency membership alone must not raise an agent's access level:
        // show() applies the same rule via view_sensitive_cnic.
        if ($canSeeSensitive) {
            $property->makeVisible(['purchasePricePKR', 'sellingPricePKR', 'internalNotes']);
            $payload = $property->toArray();
        }
        return $payload;
    }

    private function publicPropertyPayload(Property $property): array
    {
        $payload = $property->toArray();
        $allowed = [
            'id', 'title', 'description', 'pricePKR', 'formattedPrice', 'location',
            'phase', 'sector', 'block', 'streetNumber', 'plotNumber', 'fileNumber',
            'sizeSqYards', 'sizeUnit', 'frontFeet', 'depthFeet', 'roadWidthFeet',
            'roadWidthFt', 'type', 'status', 'isFeatured', 'images', 'videoUrl',
            'features', 'bedrooms', 'bathrooms', 'possessionReady', 'mdaApproved',
            'cornerPlot', 'westOpen', 'parkFacing', 'mainBoulevard', 'createdDate',
            'contactPerson', 'contactPhone', 'contactWhatsApp', 'agencyName',
            'listing_purpose', 'slug', 'expires_at', 'created_at',
            'agency_featured_badge',
        ];

        $agency = $property->relationLoaded('agency') ? $property->agency : null;
        $promotion = $agency?->relationLoaded('activePromotion') ? $agency->activePromotion : null;
        if ($agency
            && $agency->status === 'active'
            && $agency->verification_status === 'verified'
            && $promotion
            && $promotion->isLive()
        ) {
            $payload['agency_featured_badge'] = $promotion->publicBadge();
        }

        return collect($allowed)
            ->filter(fn (string $key) => array_key_exists($key, $payload))
            ->mapWithKeys(fn (string $key) => [$key => $payload[$key]])
            ->all();
    }

    private function uniqueSlug(string $title, string $excludeId): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'listing-' . substr($excludeId, -8);
        }
        $slug = $base;
        $i = 2;
        while (Property::where('slug', $slug)->where('id', '!=', $excludeId)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
