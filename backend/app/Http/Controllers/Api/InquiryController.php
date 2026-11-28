<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\BusinessRecord;
use App\Models\Inquiry;
use App\Models\Property;
use App\Support\InquiryCaptcha;
use App\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

        $filters = $request->validate([
            'status' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:50',
            'mine' => 'sometimes|boolean',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $query = Inquiry::query();

        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        } elseif ($request->boolean('mine') && $request->user()->dealer_id) {
            // Legacy dealer: keep the assigned-property lookup as a SQL
            // subquery so a large property portfolio is not materialized in PHP.
            $dealerId = $request->user()->dealer_id;
            $query->where(function ($q) use ($dealerId) {
                $q->whereIn('property_id', Property::where('assignedDealerId', $dealerId)->select('id'))
                    ->orWhereNull('agency_id');
            });
        } elseif (!$this->isCrossAgency($request) && $agencyId === null) {
            // Non-platform users without an agency context must never see
            // unscoped inquiries.
            abort(403, 'No agency context for this action.');
        }

        if (!empty($filters['status']) && $filters['status'] !== 'All') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        // Keep the legacy plain-array response shape while bounding work and
        // response size for tenants with long inquiry histories.
        $limit = min((int) ($filters['limit'] ?? 1000), 1000);

        // Mask CNIC in responses
        $inquiries = $query->orderByDesc('created_at')->orderBy('id')->limit($limit)->get();
        foreach ($inquiries as $inquiry) {
            // Ensure CNIC is masked if present
            if ($inquiry->cnic) {
                $inquiry->cnic = $this->maskCnic($inquiry->cnic);
            }
        }

        return response()->json($inquiries);
    }

    /**
     * GET /api/inquiries/captcha — issue a signed, single-use, expiring
     * math challenge for the public inquiry form (G-11). No third-party
     * captcha tracker is involved; see App\Support\InquiryCaptcha.
     */
    public function captcha()
    {
        return response()->json(InquiryCaptcha::issue());
    }

    /**
     * Store a new public inquiry (guests allowed).
     *
     * When the inquiry references a listing (`propertyId` / `property_id`),
     * the listing's agency is attached server-side so the lead lands in the
     * right agency's CRM pipeline — clients never supply agency_id.
     *
     * Bot protection (G-11): submissions must carry a solved challenge plus
     * an untouched honeypot field.
     */
    public function store(Request $request)
    {
        // Honeypot: humans never see (nor fill) this field. Bots that do get
        // a convincing 201 so they do not hammer the endpoint with retries —
        // nothing is stored and no internal detail leaks.
        if (trim((string) $request->input('companyWebsite', '')) !== '') {
            return response()->json([
                'id' => 'inq-' . Str::uuid(),
                'subject' => $request->input('subject', 'Property Consultation'),
                'status' => 'Pending',
                'submittedAt' => now()->format('Y-m-d H:i'),
            ], 201);
        }

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
            'captchaToken' => 'required|string|max:1024',
            'captchaAnswer' => 'required|string|max:8',
        ]);

        // Bot gate: the answer must match the signed, single-use, unexpired
        // challenge. Failures return a validation-style 422 with a distinct
        // machine-readable code so the SPA can fetch a fresh question.
        if (!InquiryCaptcha::verify($data['captchaToken'], $data['captchaAnswer'])) {
            return response()->json([
                'success' => false,
                'code' => 'captcha_failed',
                'message' => 'The security answer was missing, wrong, or expired. Please answer the new question and resubmit.',
                'errors' => ['captchaAnswer' => ['The security answer is incorrect or has expired.']],
            ], 422);
        }
        unset($data['captchaToken'], $data['captchaAnswer']);

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

        // Serialize lead-quota checks per agency and keep the relational
        // inquiry + legacy mirror in one transaction. Otherwise parallel
        // public submissions can exceed the lead cap, or a failed relational
        // insert can leave an orphan BusinessRecord row.
        $result = DB::transaction(function () use ($property, $record): array {
            if ($property?->agency_id) {
                $agency = Agency::whereKey($property->agency_id)->lockForUpdate()->first();
                if ($agency && ($block = PlanLimits::block($agency, 'leads'))) {
                    return ['blocked' => $block];
                }
            }

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

            // Keep the legacy mirror row agency-tagged too.
            if ($property?->agency_id) {
                BusinessRecord::where('collection', 'inquiries')
                    ->where('record_id', $record['id'])
                    ->update(['agency_id' => $property->agency_id]);
            }

            return ['stored' => true];
        });

        if (isset($result['blocked'])) {
            return response()->json([
                'success' => false,
                'code' => 'plan_limit_reached',
                'message' => 'This agency is not accepting new inquiries right now. Please contact them directly.',
            ], 402);
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

        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);

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

        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);

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

        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);

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

    /**
     * Tenant + agent-level scope for CRM mutations. Without this, an agent
     * could mutate leads assigned to a colleague in the same agency.
     */
    private function findScopedForAgent(Request $request, string $id): Inquiry
    {
        $agencyId = $this->agencyId($request);
        $query = Inquiry::query();
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        } elseif (!$this->isCrossAgency($request)) {
            // Non-platform users without an agency context must never see
            // unscoped inquiries.
            abort(403, 'No agency context for this action.');
        }
        $inquiry = $query->findOrFail($id);

        $canSeeAll = $this->can($request, 'crm.assign_lead')
            || $this->can($request, 'crm.delete_lead');
        $userId = (int) ($request->user()?->id ?? 0);
        if (!$canSeeAll
            && $inquiry->assigned_agent_id !== null
            && (int) $inquiry->assigned_agent_id !== $userId) {
            abort(404, 'Inquiry not found.');
        }

        return $inquiry;
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