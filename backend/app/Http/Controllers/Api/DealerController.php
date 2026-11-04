<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    use AuthorizesApi;

    /**
     * List dealers (with CNIC masking for privacy).
     */
    public function index(Request $request)
    {
        $query = Dealer::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $dealers = $query->latest()->get();

        // Mask CNIC in responses
        foreach ($dealers as $dealer) {
            if ($dealer->cnic) {
                $dealer->cnic = $this->maskCnic($dealer->cnic);
            }
        }

        return response()->json($dealers);
    }

    /**
     * Show a single dealer.
     */
    public function show($id)
    {
        $dealer = Dealer::findOrFail($id);

        // Mask CNIC
        if ($dealer->cnic) {
            $dealer->cnic = $this->maskCnic($dealer->cnic);
        }

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

        // Mask CNIC before storing
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

        // Mask CNIC if updated
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