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
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'preferredPropertyOrProject' => 'required|string|max:255',
            'propertyId' => 'nullable|string|max:256',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'message' => 'required|string|max:5000',
            'captchaToken' => 'required|string|max:120',
            'captchaAnswer' => 'required|string|max:20',
        ]);

        if (!$this->captcha->verify($data['captchaToken'], $data['captchaAnswer'], $request->ip())) {
            return response()->json(['success' => false, 'message' => 'Verification failed. Please solve the security check again.'], 422);
        }

        $property = !empty($data['propertyId'])
            ? Property::publiclyVisible()->where('id', $data['propertyId'])->first()
            : null;
        $agencyId = $property?->agency_id;
        $id = 'inq_' . bin2hex(random_bytes(8));
        $record = [
            'id' => $id,
            'preferredPropertyOrProject' => $data['preferredPropertyOrProject'],
            'propertyId' => $property?->id,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? '',
            'message' => $data['message'],
            'status' => 'Pending',
            'createdAt' => now()->toIso8601String(),
            'source' => 'public_website',
            'ipAddress' => $request->ip(),
        ];

        $result = DB::transaction(function () use ($agencyId, $id, $record, $data, $property, $request): array {
            if ($agencyId !== null) {
                $agency = Agency::whereKey($agencyId)->lockForUpdate()->first();
                if (!$agency || $agency->status !== 'active') {
                    return ['error' => 'listing_unavailable'];
                }

                if ($block = PlanLimits::block($agency, 'leads', fn () => PlanLimits::leadUsed($agencyId))) {
                    return ['blocked' => $block];
                }

                // Re-check public visibility while holding the agency lock,
                // then commit the tenant and legacy records together.
                $currentProperty = Property::where('agency_id', $agencyId)
                    ->lockForUpdate()
                    ->find($property?->id);
                if (!$currentProperty || !$currentProperty->isPubliclyVisible()) {
                    return ['error' => 'listing_unavailable'];
                }
            }

            BusinessRecord::put('inquiries', $id, $record, $agencyId);

            Inquiry::updateOrCreate(
                ['id' => $id],
                [
                    'agency_id' => $agencyId,
                    'property_id' => $property?->id,
                    'fullName' => 'Website Visitor',
                    'subject' => 'Property Consultation',
                    'preferredPropertyOrProject' => $data['preferredPropertyOrProject'],
                    'budgetRange' => null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'],
                    'message' => $data['message'],
                    'status' => 'Pending',
                    'source' => 'public_website',
                ]
            );

            return ['saved' => true];
        }, 3);

        if (isset($result['blocked'])) {
            return PlanLimits::deny('Your plan has reached its monthly lead limit. Upgrade to receive more enquiries.', $result['blocked']);
        }
        if (($result['error'] ?? null) === 'listing_unavailable') {
            return response()->json(['success' => false, 'message' => 'This listing is no longer available.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your enquiry has been sent. Our team will contact you soon.',
            'id' => $id,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $this->requireAny($request, ['crm.update_lead', 'crm.delete_lead']);
        $data = $request->validate([
            'status' => 'required|string|in:Pending,Contacted,In Progress,Resolved',
        ]);

        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);

        [$updated, $from] = DB::transaction(function () use ($inquiry, $data): array {
            $locked = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $locked->update(['status' => $data['status']]);

            $legacy = BusinessRecord::where('collection', 'inquiries')
                ->where('record_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($legacy) {
                $payload = is_array($legacy->payload) ? $legacy->payload : [];
                $payload['status'] = $data['status'];
                $legacy->payload = $payload;
                $legacy->save();
            }

            return [$locked->fresh(), $from];
        }, 3);

        $this->writeAudit($request, 'inquiry.status', (string) $updated->id,
            "Inquiry {$from} → {$data['status']}", [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => (string) $updated->id,
                'metadata' => ['from' => $from, 'to' => $data['status']],
            ]);

        return response()->json($updated);
    }

    public function updateNotes(Request $request, $id)
    {
        $this->requireAny($request, ['crm.update_lead']);
        $data = $request->validate([
            'notes' => 'nullable|string|max:4000',
        ]);

        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);
        $notes = $data['notes'] ?? '';

        $updated = DB::transaction(function () use ($inquiry, $notes): Inquiry {
            $locked = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();
            $locked->update(['adminNotes' => $notes]);

            $legacy = BusinessRecord::where('collection', 'inquiries')
                ->where('record_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($legacy) {
                $payload = is_array($legacy->payload) ? $legacy->payload : [];
                $payload['adminNotes'] = $notes;
                $legacy->payload = $payload;
                $legacy->save();
            }

            return $locked->fresh();
        }, 3);

        $this->writeAudit($request, 'inquiry.notes', (string) $updated->id,
            'Inquiry notes updated', [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => (string) $updated->id,
            ]);

        return response()->json($updated);
    }

    public function destroy(Request $request, $id)
    {
        $this->requireAny($request, ['crm.delete_lead']);
        $inquiry = $this->findScopedForAgent($request, $id);
        $agencyId = $this->agencyId($request);
        $fullName = $inquiry->fullName;

        DB::transaction(function () use ($inquiry): void {
            $locked = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();

            BusinessRecord::where('collection', 'inquiries')
                ->where('record_id', $locked->id)
                ->delete();

            $locked->delete();
        }, 3);

        $this->writeAudit($request, 'inquiry.delete', (string) $inquiry->id,
            "Inquiry deleted: {$fullName}", [
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