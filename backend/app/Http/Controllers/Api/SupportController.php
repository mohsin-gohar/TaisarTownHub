<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Module 15 — platform support desk.
 *
 * Any authenticated account can raise a ticket (self-service support).
 * Visibility is server-scoped: a member sees their agency's tickets, a
 * platform-side account sees its own. Staff actions (list all / assign /
 * reply as staff / set status) require the `admin.support_tickets`
 * permission. Ticket ids and agency links are resolved server-side —
 * tenant ids from the client are never trusted.
 */
class SupportController extends Controller
{
    use AuthorizesApi;

    /* ==================== Agency / self-service side ==================== */

    /** GET /api/support/tickets — my agency's tickets (or my own platform tickets). */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::query()->with(['creator:id,name', 'assignee:id,name']);

        if ($agencyId = $this->agencyId($request)) {
            $query->where('agency_id', $agencyId);
        } else {
            $query->where('user_id', $user->id)->whereNull('agency_id');
        }

        if ($request->filled('status') && in_array($request->status, SupportTicket::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category') && in_array($request->category, SupportTicket::CATEGORIES, true)) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where('subject', 'like', "%{$s}%");
        }

        return response()->json([
            'success' => true,
            // Deterministic order: newest first, insertion order as tiebreaker.
            // `created_at` only has second precision, so two tickets raised in
            // the same second would otherwise come back in an arbitrary order.
            'tickets' => $query->orderByDesc('created_at')->orderBy('id')->limit(100)->get()->map(fn (SupportTicket $t) => $this->present($t)),
        ]);
    }

    /** POST /api/support/tickets — raise a ticket (first message included). */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'subject' => 'required|string|min:5|max:190',
            'body' => 'required|string|min:10|max:5000',
            'category' => 'nullable|string|in:' . implode(',', SupportTicket::CATEGORIES),
            'priority' => 'nullable|string|in:' . implode(',', SupportTicket::PRIORITIES),
        ]);

        // Agency link comes from the membership context, never from input.
        $agencyId = $this->agencyId($request);

        $ticket = SupportTicket::create([
            'id' => 'tkt_' . bin2hex(random_bytes(8)),
            'agency_id' => $agencyId,
            'user_id' => $user->id,
            'subject' => strip_tags($data['subject']),
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_staff' => false,
            'body' => strip_tags($data['body']),
            'created_at' => now(),
        ]);

        $this->writeAudit($request, 'support.ticket_created', $ticket->id,
            "Support ticket: {$ticket->subject}", [
                'agency_id' => $agencyId,
                'entity_type' => 'support_ticket',
                'entity_id' => $ticket->id,
                'metadata' => ['category' => $ticket->category, 'priority' => $ticket->priority],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created. Our support team will respond soon.',
            'ticket' => $this->present($ticket->fresh()),
        ], 201);
    }

    /** GET /api/support/tickets/{id} — ticket with its full message thread. */
    public function show(Request $request, string $id)
    {
        $ticket = $this->findVisible($request, $id);

        $ticket->load(['creator:id,name', 'assignee:id,name']);

        $messages = $ticket->messages()->with('author:id,name')->get()->map(fn (SupportMessage $m) => [
            'id' => $m->id,
            'body' => $m->body,
            'is_staff' => $m->is_staff,
            'author_name' => $m->author?->name,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'ticket' => $this->present($ticket),
            'messages' => $messages,
        ]);
    }

    /** POST /api/support/tickets/{id}/messages — add a message to the thread. */
    public function storeMessage(Request $request, string $id)
    {
        $user = $request->user();
        $ticket = $this->findVisible($request, $id);

        $isStaff = $this->can($request, 'admin.support_tickets');

        $data = $request->validate([
            'body' => 'required|string|min:2|max:5000',
        ]);

        if ($ticket->status === 'closed' && !$isStaff) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is closed. Raise a new ticket if the issue persists.',
            ], 422);
        }

        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_staff' => $isStaff,
            'body' => strip_tags($data['body']),
            'created_at' => now(),
        ]);

        // First staff reply stamps the response-time metric; a user reply on a
        // resolved ticket re-opens it for staff attention.
        if ($isStaff) {
            $ticket->first_response_at = $ticket->first_response_at ?? now();
            if ($ticket->status === 'resolved') {
                $ticket->status = 'open';
                $ticket->resolved_at = null;
            }
            $ticket->save();
        } elseif (in_array($ticket->status, ['resolved'], true)) {
            $ticket->status = 'open';
            $ticket->resolved_at = null;
            $ticket->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Message added.',
            'message_entry' => [
                'id' => $message->id,
                'body' => $message->body,
                'is_staff' => $message->is_staff,
                'author_name' => $user->name,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * POST /api/support/tickets/{id}/status — transition the ticket state.
     * Creators may close/reopen their own tickets; staff may set any state.
     */
    public function updateStatus(Request $request, string $id)
    {
        $user = $request->user();
        $ticket = $this->findVisible($request, $id);

        $isStaff = $this->can($request, 'admin.support_tickets');
        $isCreator = $ticket->user_id === $user->id;

        if (!$isStaff && !$isCreator) {
            abort(403, 'Only the ticket creator or support staff can change its status.');
        }

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', SupportTicket::STATUSES),
        ]);

        $status = $data['status'];
        $ticket->status = $status;
        $ticket->closed_at = $status === 'closed' ? now() : null;
        $ticket->resolved_at = $status === 'resolved' ? now() : null;
        if ($status === 'resolved' && $ticket->first_response_at === null && $isStaff) {
            $ticket->first_response_at = now();
        }
        $ticket->save();

        $this->writeAudit($request, 'support.ticket_status', $ticket->id,
            "Ticket status → {$status}: {$ticket->subject}", [
                'agency_id' => $ticket->agency_id,
                'entity_type' => 'support_ticket',
                'entity_id' => $ticket->id,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated.',
            'ticket' => $this->present($ticket),
        ]);
    }

    /* ==================== Platform staff side ==================== */

    /** GET /api/admin/support-tickets — every ticket, filterable (staff). */
    public function adminIndex(Request $request)
    {
        $this->requirePermission($request, 'admin.support_tickets');

        $query = SupportTicket::query()->with(['creator:id,name', 'assignee:id,name', 'agency:id,name']);

        if ($request->filled('status') && in_array($request->status, SupportTicket::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category') && in_array($request->category, SupportTicket::CATEGORIES, true)) {
            $query->where('category', $request->category);
        }
        if ($request->filled('priority') && in_array($request->priority, SupportTicket::PRIORITIES, true)) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(fn ($q) => $q->where('subject', 'like', "%{$s}%"));
        }

        return response()->json([
            'success' => true,
            // Same deterministic ordering as the agency-side list.
            'tickets' => $query->orderByDesc('created_at')->orderBy('id')->limit(200)->get()->map(fn (SupportTicket $t) => array_merge(
                $this->present($t),
                ['agency_name' => $t->agency?->name]
            )),
        ]);
    }

    /**
     * GET /api/admin/support-tickets/assignable-staff — the platform-side
     * accounts a ticket may be assigned to. Only names/emails/roles are
     * exposed, never password hashes, tokens or MFA material.
     */
    public function assignableStaff(Request $request)
    {
        $this->requirePermission($request, 'admin.support_tickets');

        $staff = User::query()
            ->where('status', 'active')
            ->whereIn('role', ['super_admin', 'admin', 'support', 'operator', 'manager'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json([
            'success' => true,
            'staff' => $staff,
        ]);
    }

    /** POST /api/admin/support-tickets/{id}/assign — hand the ticket to a staff member. */
    public function adminAssign(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.support_tickets');

        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereIn('role', ['super_admin', 'admin', 'support', 'operator', 'manager'])),
            ],
        ]);

        $ticket->forceFill(['assigned_to' => $data['assigned_to']])->save();

        $this->writeAudit($request, 'support.ticket_assigned', $ticket->id,
            "Ticket assigned to user #{$data['assigned_to']}", [
                'agency_id' => $ticket->agency_id,
                'entity_type' => 'support_ticket',
                'entity_id' => $ticket->id,
                'metadata' => ['assigned_to' => (int) $data['assigned_to']],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned.',
            'ticket' => $this->present($ticket->fresh(['assignee:id,name'])),
        ]);
    }

    /* ==================== Internals ==================== */

    /** Ticket the requester may see: own-agency (or own), or staff-any. */
    private function findVisible(Request $request, string $id): SupportTicket
    {
        $user = $request->user();

        $ticket = SupportTicket::where('id', $id)->first();

        if (!$ticket) {
            abort(404, 'Ticket not found.');
        }

        if ($this->can($request, 'admin.support_tickets')) {
            return $ticket; // staff sees everything
        }

        $agencyId = $this->agencyId($request);
        $sameAgency = $agencyId !== null && $ticket->agency_id === $agencyId;
        $own = $ticket->user_id === $user->id;

        if (!$sameAgency && !$own) {
            abort(404, 'Ticket not found.'); // enumeration-safe
        }

        return $ticket;
    }

    private function present(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'category' => $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'agency_id' => $t->agency_id,
            'created_by' => $t->creator?->name,
            'assigned_to' => $t->assignee?->id,
            'assigned_to_name' => $t->assignee?->name,
            'first_response_at' => $t->first_response_at?->toIso8601String(),
            'resolved_at' => $t->resolved_at?->toIso8601String(),
            'closed_at' => $t->closed_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
