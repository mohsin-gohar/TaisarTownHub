<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\LeadActivity;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CRM pipeline for agency leads (inquiries).
 *
 * Record-level rule: users without `crm.assign_lead` (agents) only see leads
 * assigned to them or still unassigned; everyone else with crm.view sees the
 * whole agency pipeline.
 */
class LeadController extends Controller
{
    use AuthorizesApi;

    public const LEAD_STATUSES = ['new', 'contacted', 'interested', 'negotiation', 'converted', 'lost'];

    public function index(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.view');

        $user = $request->user();
        $query = Inquiry::where('agency_id', $agencyId);

        $canSeeAll = $this->can($request, 'crm.assign_lead');
        if (!$canSeeAll) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_agent_id', $user->id)->orWhereNull('assigned_agent_id');
            });
        }

        if ($request->filled('lead_status')) {
            $query->where('lead_status', $request->lead_status);
        }
        if ($request->filled('assigned_agent_id')) {
            $query->where('assigned_agent_id', $request->assigned_agent_id);
        }
        if ($request->boolean('follow_up_due')) {
            $query->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now());
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('fullName', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('message', 'like', "%{$s}%");
            });
        }

        $page = $query->with(['assignedAgent:id,name', 'property:id,title'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return response()->json([
            'success' => true,
            'leads' => $page->items(),
            'pipeline' => $this->pipelineCounts($agencyId, $canSeeAll ? null : $user->id),
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
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.view');

        $lead = $this->findScoped($agencyId, $id, $request->user());

        return response()->json([
            'success' => true,
            'lead' => $lead,
            'activities' => $lead->activities()->with('actor:id,name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.create_lead');

        $request->validate([
            'fullName' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:30',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:4000',
            'budgetRange' => 'nullable|string|max:100',
            'preferredPropertyOrProject' => 'nullable|string|max:255',
            'property_id' => 'nullable|string|max:256',
            'lead_status' => 'nullable|in:' . implode(',', self::LEAD_STATUSES),
            'source' => 'nullable|string|max:60',
        ]);

        if ($request->filled('property_id')) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        $lead = Inquiry::create([
            'id' => 'inq_' . bin2hex(random_bytes(8)),
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject ?: 'Direct lead',
            'message' => $request->message ?: '',
            'budgetRange' => $request->budgetRange,
            'preferredPropertyOrProject' => $request->preferredPropertyOrProject,
            'status' => 'Pending',
            'source' => $request->source ?: 'Manual entry',
            'agency_id' => $agencyId,
            'property_id' => $request->property_id,
            'lead_status' => $request->get('lead_status', 'new'),
        ]);

        $this->activity($lead, $agencyId, $request, 'note', 'Lead created manually by ' . $request->user()->name);
        $this->writeAudit($request, 'lead.create', $lead->id, "Lead created: {$lead->fullName}", [
            'agency_id' => $agencyId,
            'entity_type' => 'inquiry',
            'entity_id' => $lead->id,
        ]);

        return response()->json(['success' => true, 'lead' => $lead], 201);
    }

    public function update(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.update_lead');

        $lead = $this->findScoped($agencyId, $id, $request->user());

        $request->validate([
            'lead_status' => 'sometimes|in:' . implode(',', self::LEAD_STATUSES),
            'status' => 'sometimes|in:Pending,In Progress,Resolved,Closed',
            'follow_up_at' => 'nullable|date',
            'adminNotes' => 'nullable|string|max:4000',
            'budgetRange' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'fullName' => 'sometimes|string|max:255',
            'message' => 'nullable|string|max:4000',
        ]);

        $payload = $request->only(['status', 'adminNotes', 'budgetRange', 'email', 'phone', 'fullName', 'message']);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $statusChanged = $request->has('lead_status') && $request->lead_status !== $lead->lead_status;

        if ($statusChanged) {
            $from = $lead->lead_status;
            $to = $request->lead_status;
            $payload['lead_status'] = $to;

            if ($to === 'converted') {
                $payload['converted_at'] = now();
            }
            if ($to === 'lost' || $to === 'converted') {
                $payload['follow_up_at'] = null;
            }

            $lead->fill($payload);
            $lead->save();

            $this->activity($lead, $agencyId, $request, 'status_change',
                "Status: {$from} → {$to}" . ($request->filled('adminNotes') ? " — {$request->adminNotes}" : ''));

            $this->writeAudit($request, 'lead.status', $lead->id, "Lead {$lead->fullName}: {$from} → {$to}", [
                'agency_id' => $agencyId,
                'entity_type' => 'inquiry',
                'entity_id' => $lead->id,
                'metadata' => ['from' => $from, 'to' => $to],
            ]);
        } else {
            unset($payload['lead_status']);
            $lead->fill($payload);
            $lead->save();

            if ($payload) {
                $this->activity($lead, $agencyId, $request, 'note', 'Lead details updated: ' . implode(', ', array_keys($payload)));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead updated.',
            'lead' => $lead,
        ]);
    }

    public function assign(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.assign_lead');

        $request->validate([
            'agent_id' => 'nullable|integer',
        ]);

        $lead = $this->findScoped($agencyId, $id, $request->user());

        $agentName = null;
        if ($request->filled('agent_id')) {
            $member = AgencyMember::where('agency_id', $agencyId)
                ->where('user_id', $request->agent_id)
                ->where('status', 'active')
                ->with('user:id,name')
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'That user is not an active member of your agency.',
                ], 422);
            }
            $agentName = $member->user?->name;
        }

        $lead->assigned_agent_id = $request->agent_id ?: null;
        $lead->save();

        $this->activity($lead, $agencyId, $request, 'assignment',
            $agentName ? "Assigned to {$agentName}" : 'Unassigned');

        $this->writeAudit($request, 'lead.assign', $lead->id, "Lead {$lead->fullName} assigned", [
            'agency_id' => $agencyId,
            'entity_type' => 'inquiry',
            'entity_id' => $lead->id,
            'metadata' => ['agent_id' => $request->agent_id],
        ]);

        return response()->json(['success' => true, 'message' => 'Lead assignment updated.', 'lead' => $lead]);
    }

    /** POST /api/agency/leads/{id}/activities — notes & follow-ups. */
    public function storeActivity(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.update_lead');

        $request->validate([
            'type' => 'nullable|in:note,followup,call,whatsapp,email',
            'body' => 'required|string|max:4000',
            'due_at' => 'nullable|date',
        ]);

        $lead = $this->findScoped($agencyId, $id, $request->user());

        $activity = $this->activity(
            $lead,
            $agencyId,
            $request,
            $request->get('type', 'note'),
            $request->body,
            $request->due_at ? now()->parse($request->due_at) : null
        );

        if ($request->filled('due_at')) {
            $lead->follow_up_at = now()->parse($request->due_at);
            $lead->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity logged.',
            'activity' => $activity,
        ], 201);
    }

    public function destroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.delete_lead');

        $lead = $this->findScoped($agencyId, $id, $request->user());

        $this->writeAudit($request, 'lead.delete', $lead->id, "Lead deleted: {$lead->fullName}", [
            'agency_id' => $agencyId,
            'entity_type' => 'inquiry',
            'entity_id' => $lead->id,
        ]);

        $lead->delete();

        return response()->json(['success' => true, 'message' => 'Lead deleted.']);
    }

    /** GET /api/agency/leads/export — CSV export (crm.export). */
    public function export(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'crm.export');

        $user = $request->user();
        $query = Inquiry::where('agency_id', $agencyId);

        // Export permission is independent from lead-assignment permission.
        // Agents granted export access must still receive only their own and
        // unassigned leads, matching the CRM list and record-level policy.
        if (!$this->can($request, 'crm.assign_lead')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_agent_id', $user->id)
                    ->orWhereNull('assigned_agent_id');
            });
        }

        $exportCount = (clone $query)->count();

        $this->writeAudit($request, 'lead.export', 'agency:' . $agencyId, 'CRM export (' . $exportCount . ' leads)', [
            'agency_id' => $agencyId,
            'metadata' => ['count' => min($exportCount, 10000)],
        ]);

        // Stream rows from the database cursor instead of materializing up to
        // 10,000 Eloquent models and a second full CSV string in PHP memory.
        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id', 'name', 'email', 'phone', 'lead_status', 'source',
                'property', 'budget', 'follow_up', 'created_at', 'notes',
            ]);

            $rows = (clone $query)
                ->select([
                    'id', 'fullName', 'email', 'phone', 'lead_status', 'source',
                    'preferredPropertyOrProject', 'budgetRange', 'follow_up_at',
                    'created_at', 'adminNotes',
                ])
                ->orderByDesc('created_at')
                ->orderBy('id')
                ->limit(10000)
                ->cursor();

            foreach ($rows as $lead) {
                fputcsv($handle, array_map(fn ($cell) => $this->csvSafeCell($cell), [
                    $lead->id, $lead->fullName, $lead->email, $lead->phone,
                    $lead->lead_status, $lead->source, $lead->preferredPropertyOrProject,
                    $lead->budgetRange, $lead->follow_up_at, $lead->created_at,
                    $lead->adminNotes,
                ]));
            }

            fclose($handle);
        }, 'leads-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /* ==================== Helpers ==================== */

    /** Neutralise spreadsheet formula injection in exported CRM fields. */
    private function csvSafeCell($value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && preg_match('/^[\=\+\-@\t\r]/', $text)) {
            return "'" . $text;
        }
        return $text;
    }

    private function findScoped(string $agencyId, string $id, $user): Inquiry
    {
        $lead = Inquiry::where('agency_id', $agencyId)->where('id', $id)->first();

        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        // Agents may only touch their own/unassigned leads
        $request = request();
        $canSeeAll = $this->can($request, 'crm.assign_lead')
            || $this->can($request, 'crm.delete_lead');

        if (!$canSeeAll
            && $lead->assigned_agent_id !== null
            && (int) $lead->assigned_agent_id !== (int) $user->id) {
            abort(404, 'Lead not found.');
        }

        return $lead;
    }

    private function activity(Inquiry $lead, string $agencyId, Request $request, string $type, string $body, $dueAt = null): LeadActivity
    {
        $activity = LeadActivity::create([
            'agency_id' => $agencyId,
            'inquiry_id' => $lead->id,
            'type' => $type,
            'body' => $body,
            'due_at' => $dueAt,
            'actor_user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);

        return $activity;
    }

    private function pipelineCounts(string $agencyId, ?int $restrictToUserId): array
    {
        $base = Inquiry::where('agency_id', $agencyId);
        if ($restrictToUserId !== null) {
            $base->where(function ($q) use ($restrictToUserId) {
                $q->where('assigned_agent_id', $restrictToUserId)->orWhereNull('assigned_agent_id');
            });
        }

        return $base->selectRaw('lead_status, COUNT(*) as c')
            ->groupBy('lead_status')
            ->pluck('c', 'lead_status')
            ->union(array_fill_keys(self::LEAD_STATUSES, 0))
            ->toArray();
    }
}
