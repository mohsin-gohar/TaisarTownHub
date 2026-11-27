<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    use AuthorizesApi;

    /**
     * List dealers. CNIC is never serialized — the Dealer model declares
     * `protected $hidden = ['cnic']`, so the full number never leaves the
     * server regardless of who calls this endpoint.
     */
    public function index(Request $request)
    {
        $query = Dealer::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Public directory: bounded page (plain-array shape kept for the
        // existing frontend). Newest first; ?limit narrows further.
        $limit = $request->filled('limit') ? (int) $request->limit : 200;
        $dealers = $query->orderByDesc('created_at')->orderBy('id')
            ->limit(min(max($limit, 1), 500))->get();

        // Public endpoint: strip financial/PII fields. Only expose safe display
        // fields to unauthenticated visitors.
        $safe = $dealers->map(fn (Dealer $d) => [
            'id' => $d->id,
            'fullName' => $d->fullName,
            'agencyAffiliation' => $d->agencyAffiliation,
            'agencyOrBranch' => $d->agencyOrBranch,
            'role' => $d->role,
            'status' => $d->status,
            'avatar' => $d->avatar,
            'assignedPropertiesCount' => $d->assignedPropertiesCount,
            'completedDealsCount' => $d->completedDealsCount,
        ]);

        return response()->json($safe);
    }

    /**
     * Show a single dealer. CNIC stays hidden via the model's $hidden array.
     */
    public function show($id)
    {
        $dealer = Dealer::findOrFail($id);

        // Public endpoint: strip financial/PII fields.
        return response()->json([
            'id' => $dealer->id,
            'fullName' => $dealer->fullName,
            'agencyAffiliation' => $dealer->agencyAffiliation,
            'agencyOrBranch' => $dealer->agencyOrBranch,
            'role' => $dealer->role,
            'status' => $dealer->status,
            'avatar' => $dealer->avatar,
            'assignedPropertiesCount' => $dealer->assignedPropertiesCount,
            'completedDealsCount' => $dealer->completedDealsCount,
        ]);
    }

    /**
     * Create a new dealer with validation.
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['manage_dealers']);

        $data = $request->validate([
            'id' => 'required|string|unique:dealers,id',
            'fullName' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'cnic' => 'nullable|string|max:20',
            'agencyAffiliation' => 'nullable|string|max:255',
            'agencyOrBranch' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'commissionType' => 'nullable|string|in:Fixed,Percentage,Custom',
            'commissionRate' => [
                'nullable', 'numeric', 'min:0', 'max:9999999999',
                function (string $attribute, mixed $value, \\Closure $fail) use ($request) {
                    if ($request->input('commissionType', 'Fixed') === 'Percentage' && $value > 100) {
                        $fail('Percentage commission cannot exceed 100%.');
                    }
                },
            ],
            'status' => 'nullable|string|max:50',
            'avatar' => 'nullable|string',
            'agencyId' => 'nullable|string|max:64|exists:agencies,id',
        ]);

        // Link the dealer to an agency. Without this the row was created with
        // agency_id = NULL and could never be assigned to an agency property
        // (PropertyController::assertDealerInAgency rejects unlinked dealers).
        // Agency-scoped publishers may only create dealers for their own
        // agency; a platform publisher must name the agency explicitly.
        $actingAgencyId = $this->agencyId($request);
        if ($actingAgencyId !== null) {
            $data['agency_id'] = $actingAgencyId;
        } elseif (!empty($data['agencyId'])) {
            $data['agency_id'] = $data['agencyId'];
        }
        unset($data['agencyId']);

        // Persist the masked copy for display, but NEVER overwrite the raw CNIC
        // column with its own masked form — that permanently destroys the
        // national ID and makes later re-masking impossible. The raw value is
        // what the ERP needs; the masked value is what UI surfaces show.
        if (!empty($data['cnic'])) {
            $data['cnicMasked'] = $this->maskCnic($data['cnic']);
        }

        $dealer = Dealer::create($data);
        $this->writeAudit($request, 'dealer_created', $dealer->id, "Dealer {$dealer->fullName} created");

        return response()->json($dealer, 201);
    }

    /**
     * Update a dealer.
     */
    public function update(Request $request, $id)
    {
        $this->requireAny($request, ['manage_dealers']);

        $dealer = Dealer::findOrFail($id);
        $commissionType = $request->input('commissionType', $dealer->commissionType ?? 'Fixed');

        $data = $request->validate([
            'fullName' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'cnic' => 'nullable|string|max:20',
            'agencyAffiliation' => 'nullable|string|max:255',
            'agencyOrBranch' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'commissionType' => 'nullable|string|in:Fixed,Percentage,Custom',
            'commissionRate' => [
                'nullable', 'numeric', 'min:0', 'max:9999999999',
                function (string $attribute, mixed $value, \\Closure $fail) use ($commissionType) {
                    if ($commissionType === 'Percentage' && $value > 100) {
                        $fail('Percentage commission cannot exceed 100%.');
                    }
                },
            ],
            'status' => 'nullable|string|max:50',
            'avatar' => 'nullable|string',
            'agencyId' => 'nullable|string|max:64|exists:agencies,id',
        ]);

        // Tenancy: an agency-scoped publisher can only touch dealers that
        // already belong to that agency, and cannot move them elsewhere.
        // Platform publishers may re-link a dealer (or pass null to unlink).
        $actingAgencyId = $this->agencyId($request);
        if ($actingAgencyId !== null) {
            if ($dealer->agency_id !== $actingAgencyId) {
                abort(404);
            }
            unset($data['agencyId']);
            $data['agency_id'] = $actingAgencyId;
        } elseif (array_key_exists('agencyId', $data)) {
            $data['agency_id'] = $data['agencyId'];
            unset($data['agencyId']);
        }

        // Persist the masked copy for display, but never overwrite the raw CNIC
        // column with its own masked form (data destruction).
        if (!empty($data['cnic'])) {
            $data['cnicMasked'] = $this->maskCnic($data['cnic']);
        }

        $dealer->update($data);
        $this->writeAudit($request, 'dealer_updated', $dealer->id, "Dealer {$dealer->fullName} updated");

        return response()->json($dealer);
    }

    /**
     * Soft delete a dealer.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAny($request, ['manage_dealers']);

        $dealer = Dealer::findOrFail($id);
        $dealer->delete();
        $this->writeAudit($request, 'dealer_deleted', $dealer->id, "Dealer {$dealer->fullName} soft-deleted");

        return response()->json(['message' => 'Dealer deleted successfully']);
    }

    private function maskCnic(string $cnic): string
    {
        $clean = preg_replace('/[^0-9]/', '', $cnic);

        // Keep the familiar 13-digit mask (42101****5671). Anything shorter is
        // fully masked: it may be a partial CNIC, and computing
        // strlen($clean) - 9 for 7-8 digit input produced a negative
        // str_repeat() length, which throws ValueError on PHP 8.
        if (strlen($clean) === 13) {
            return substr($clean, 0, 5) . str_repeat('*', 4) . substr($clean, -4);
        }

        return str_repeat('*', strlen($clean));
    }
}

