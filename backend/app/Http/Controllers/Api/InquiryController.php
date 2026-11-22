<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\BusinessRecord;
use App\Models\Inquiry;
use App\Models\Property;
use App\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryController extends Controller
{
    use AuthorizesApi;

    /**
     * List inquiries with filtering.
     * Agency members only ever see their own agency's leads.
     */
    public function index(Request $request)
    {
        $this->requireAny($request, ['view_inquiries', 'crm.view']);

        $query = Inquiry::query();

        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        } elseif ($request->boolean('mine') && $request->user()->dealer_id) {
            // Legacy dealer: only leads on their own assigned properties
            $myPropertyIds = Property::where('assignedDealerId', $request->user()->dealer_id)->pluck('id');
            $query->where(function ($q) use ($myPropertyIds) {
                $q->whereIn('property_id', $myPropertyIds)
                    ->orWhere('agency_id', null);
            });
        }

        if ($request->filled('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Mask CNIC in responses
        $inquiries = $query->orderByDesc('created_at')->orderBy('id')->get();
        foreach ($inquiries as $inquiry) {
            // Ensure CNIC is masked if present
            if ($inquiry->cnic) {
                $inquiry->cnic = $this->maskCnic($inquiry->cnic);
            }
        }

        return response()->json($inquiries);
    }

    /**
     * Store a new public inquiry (guests allowed).
     *
     * When the inquiry references a listing (`propertyId` / `property_id`),
     * the listing's agency is attached server-side so the lead lands in the
     * right agency's CRM pipeline — clients never supply agency_id.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fullName' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:2000',
            'email' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'preferredPropertyOrProject' => 'nullable|string|max:255',
            'budgetRange' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:50',
            'propertyId' => 'nullable|string|max:256',
            'property_id' => 'nullable|string|max:256',
        ]);

        // Sanitize input
        $data['fullName'] = strip_tags($data['fullName']);
        $data['message'] = strip_tags($data['message']);

        // Resolve the referenced listing → its agency owns this lead.
        $propertyId = $data['property_id'] ?? $data['propertyId'] ?? null;
        unset($data['property_id'], $data['propertyId']);

        $property = null;
        if ($propertyId) {
            $property = Property::publiclyVisible()->where('id', $propertyId)->first();
            if (!$property) {
                // Never leak whether a foreign/nonexistent id exists
                $property = null;
            }
        }

        // G-8: plan limit — an agency whose lead quota is exhausted stops
        // receiving public leads. The public message stays generic (no
        // internal quota details leak to visitors).
        if ($property && $property->agency_id) {
            $leadAgency = Agency::find($property->agency_id);
            if ($leadAgency && ($block = PlanLimits::block($leadAgency, 'leads'))) {
                return response()->json([
                    'success' => false,
                    'code' => 'plan_limit_reached',
                    'message' => 'This agency is not accepting new inquiries right now. Please contact them directly.',
                ], 402);
            }
        }

        $record = [
            'id' => 'inq-' . Str::uuid(),
            'fullName' => $data['fullName'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'subject' => $data['subject'] ?? 'Property Consultation',
            'message' => $data['message'],
            'preferredPropertyOrProject' => $data['preferredPropertyOrProject']
                ?? ($property?->title),
            'budgetRange' => $data['budgetRange'] ?? null,
            'status' => 'Pending',
            'adminNotes' => 'Received via the public website.',
            'source' => $data['source'] ?? 'React Web Portal',
            'submittedAt' => now()->format('Y-m-d H:i'),
        ];
        BusinessRecord::put('inquiries', $record['id'], $record);

        Inquiry::updateOrCreate(['id' => $record['id']], [
            'fullName' => $record['fullName'],
            'email' => $record['email'],
            'phone' => $record['phone'],
            'subject' => $record['subject'],
            'message' => $record['message'],
            'preferredPropertyOrProject' => $record['preferredPropertyOrProject'],
            'budgetRange' => $record['budgetRange'],
            'status' => 'Pending',
            'adminNotes' => $record['adminNotes'],
            'source' => $record['source'],
            'agency_id' => $property?->agency_id,
            'property_id' => $property?->id,
            'lead_status' => 'new',
        ]);

        // Keep the legacy mirror row agency-tagged too
        if ($property?->agency_id) {
            BusinessRecord::where('collection', 'inquiries')
                ->where('record_id', $record['id'])
                ->update(['agency_id' => $property->agency_id]);
        }

        return response()->json($record, 201);
    }

    /**
     * Update inquiry status.
     *
     * Requires a write-level CRM permission — read-only `view_inquiries` /
     * `crm.view` must never be able to mutate inquiry state.
     */
    public function updateStatus(Request $request, $id)
    {
        $this->requireAny($request, ['crm.update_lead', 'crm.delete_lead']);

        $request->validate([
            'status' => 'required|string|in:Pending,Contacted,In Progress,Resolved',
        ]);

        $query = Inquiry::query();
        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }
        $inquiry = $query->findOrFail($id);

        $from = $inquiry->status;
        $inquiry->update(['status' => $request->status]);

        // Keep the legacy BusinessRecord mirror in sync.
        \App\Models\BusinessRecord::where('collection', 'inquiries')
            ->where('record_id', $inquiry->id)
            ->update(['status' => $request->status]);

        $this->writeAudit($request, 'inquiry.status', (string) $inquiry->id,
            "Inquiry {$from} → {$request->status}", [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => (string) $inquiry->id,
                'metadata' => ['from' => $from, 'to' => $request->status],
            ]);

        return response()->json($inquiry);
    }

    /**
     * PATCH /api/inquiries/{id}/notes — update internal agent notes.
     * Requires crm.update_lead.
     */
    public function updateNotes(Request $request, $id)
    {
        $this->requireAny($request, ['crm.update_lead']);

        $request->validate([
            'notes' => 'nullable|string|max:4000',
        ]);

        $agencyId = $this->agencyId($request);
        $query = Inquiry::query();
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }
        $inquiry = $query->findOrFail($id);

        $inquiry->update(['adminNotes' => $request->input('notes', '')]);

        // Keep the legacy BusinessRecord mirror in sync.
        \App\Models\BusinessRecord::where('collection', 'inquiries')
            ->where('record_id', $inquiry->id)
            ->update(['adminNotes' => $request->input('notes', '')]);

        $this->writeAudit($request, 'inquiry.notes', (string) $inquiry->id,
            "Inquiry notes updated", [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => (string) $inquiry->id,
            ]);

        return response()->json($inquiry);
    }

    /**
     * DELETE /api/inquiries/{id} — delete an inquiry.
     * Requires crm.delete_lead.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAny($request, ['crm.delete_lead']);

        $agencyId = $this->agencyId($request);
        $query = Inquiry::query();
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }
        $inquiry = $query->findOrFail($id);

        // Keep the legacy BusinessRecord mirror in sync so no orphaned
        // rows remain after deletion.
        \App\Models\BusinessRecord::where('collection', 'inquiries')
            ->where('record_id', $inquiry->id)
            ->delete();

        $inquiry->delete();

        $this->writeAudit($request, 'inquiry.delete', (string) $inquiry->id,
            "Inquiry deleted: {$inquiry->fullName}", [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => (string) $inquiry->id,
            ]);

        return response()->json(['success' => true, 'message' => 'Inquiry deleted.']);
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