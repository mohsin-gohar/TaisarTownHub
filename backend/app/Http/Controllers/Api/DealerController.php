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

        $dealers = $query->orderByDesc('created_at')->orderBy('id')->get();

        return response()->json($dealers);
    }

    /**
     * Show a single dealer. CNIC stays hidden via the model's $hidden array.
     */
    public function show($id)
    {
        $dealer = Dealer::findOrFail($id);

        return response()->json($dealer);
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
            'commissionRate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'avatar' => 'nullable|string',
        ]);

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

        $data = $request->validate([
            'fullName' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'cnic' => 'nullable|string|max:20',
            'agencyAffiliation' => 'nullable|string|max:255',
            'agencyOrBranch' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'commissionType' => 'nullable|string|in:Fixed,Percentage,Custom',
            'commissionRate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'avatar' => 'nullable|string',
        ]);

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
        if (strlen($clean) < 7) {
            return str_repeat('*', strlen($clean));
        }
        return substr($clean, 0, 5) . str_repeat('*', strlen($clean) - 9) . substr($clean, -4);
    }
}