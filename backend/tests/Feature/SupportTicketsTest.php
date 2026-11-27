<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\User;
use Tests\Feature\EnterpriseTestCase;

/**
 * Module 15 — support desk. Any authenticated account can raise a ticket;
 * the agency link is resolved server-side from the membership context.
 * Visibility is team-scoped (same agency), staff actions require
 * `admin.support_tickets`.
 */
class SupportTicketsTest extends EnterpriseTestCase
{
    private function memberUser(array $perms = ['agency.view']): array
    {
        return $this->agencyUser($perms, 'agent');
    }

    public function test_member_can_raise_a_ticket_and_client_tenant_ids_are_ignored(): void
    {
        [$user, $agency] = $this->memberUser();
        $other = $this->makeAgency('Other Agency');

        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'Cannot upload registry document',
            'body' => 'The upload fails every time with a network error.',
            'category' => 'technical',
            'priority' => 'high',
            // Attacker-supplied tenant id must be discarded.
            'agency_id' => $other->id,
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('ticket.status', 'open')
            ->assertJsonPath('ticket.category', 'technical');

        $this->assertSame($agency->id, $response->json('ticket.agency_id'));

        $ticket = SupportTicket::find($response->json('ticket.id'));
        $this->assertSame($agency->id, $ticket->agency_id);
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(1, $ticket->messages()->count(), 'the opening message is part of the thread');
    }

    public function test_visibility_is_scoped_and_staff_sees_everything(): void
    {
        [$userA, $agencyA] = $this->memberUser();
        [$userB, $agencyB] = $this->memberUser();

        $ticketA = $this->postJson('/api/support/tickets', [
            'subject' => 'Agency A billing question',
            'body' => 'Please explain the invoice period for last month.',
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(201)->json('ticket.id');

        $this->postJson('/api/support/tickets', [
            'subject' => 'Agency B wants a demo',
            'body' => 'We would like to schedule an onboarding demo.',
        ], $this->authHeaders($this->tokenFor($userB)))->assertStatus(201);

        // Member sees only their agency's tickets.
        $listB = $this->getJson('/api/support/tickets', $this->authHeaders($this->tokenFor($userB)))->assertOk();
        $subjects = array_column($listB->json('tickets'), 'subject');
        $this->assertContains('Agency B wants a demo', $subjects);
        $this->assertNotContains('Agency A billing question', $subjects);

        // Cross-agency detail access is enumeration-safe (404, not 403).
        $this->getJson('/api/support/tickets/' . $ticketA, $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(404);

        // Platform staff (super_admin) sees every ticket.
        $this->seedPlatformRole('super_admin');
        $staff = $this->makeUser('super_admin');
        $all = $this->getJson('/api/admin/support-tickets', $this->authHeaders($this->tokenFor($staff)))->assertOk();
        $this->assertSame(2, count($all->json('tickets')));
        // The staff lane must contain exactly the two agencies' tickets. The
        // assertion is order-independent on purpose: the desk lists newest
        // first and `created_at` only has second precision, so which of two
        // same-second tickets lands on index 0 is not a stable contract.
        $agencyNames = array_column($all->json('tickets'), 'agency_name');
        sort($agencyNames);
        $expectedNames = [$agencyA->name, $agencyB->name];
        sort($expectedNames);
        $this->assertSame($expectedNames, $agencyNames);

        // Agency members cannot use the admin endpoint.
        $this->getJson('/api/admin/support-tickets', $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(403);
    }

    public function test_message_threads_and_first_response_metric(): void
    {
        [$user] = $this->memberUser();

        $ticket = $this->postJson('/api/support/tickets', [
            'subject' => 'Report export broken',
            'body' => 'The CSV export downloads an empty file.',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(201)->json('ticket.id');

        $this->assertNull(SupportTicket::find($ticket)->first_response_at);

        // Member follow-up.
        $this->postJson('/api/support/tickets/' . $ticket . '/messages', [
            'body' => 'Happens on every report, even last month.',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->assertJsonPath('message_entry.is_staff', false);

        // Staff reply stamps first_response_at and marks is_staff.
        $this->seedPlatformRole('super_admin');
        $staff = $this->makeUser('super_admin');
        $this->postJson('/api/support/tickets/' . $ticket . '/messages', [
            'body' => 'Thanks — we are looking into it now.',
        ], $this->authHeaders($this->tokenFor($staff)))
            ->assertStatus(201)
            ->assertJsonPath('message_entry.is_staff', true);

        $fresh = SupportTicket::find($ticket);
        $this->assertNotNull($fresh->first_response_at);

        // Thread is ordered and both authors present.
        $detail = $this->getJson('/api/support/tickets/' . $ticket, $this->authHeaders($this->tokenFor($staff)))->assertOk();
        $this->assertSame(3, count($detail->json('messages')));
    }

    public function test_assign_status_transitions_and_permissions(): void
    {
        [$user, $agency] = $this->memberUser();
        $this->seedPlatformRole('super_admin');
        $staff = $this->makeUser('super_admin');
        $helper = $this->makeUser('operator'); // another staff account

        $ticket = $this->postJson('/api/support/tickets', [
            'subject' => 'Login loop on portal',
            'body' => 'After password change the portal keeps asking to log in.',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(201)->json('ticket.id');

        // Staff assignment (and non-staff cannot assign).
        $this->postJson('/api/admin/support-tickets/' . $ticket . '/assign', [
            'assigned_to' => $helper->id,
        ], $this->authHeaders($this->tokenFor($staff)))
            ->assertOk()
            ->assertJsonPath('ticket.assigned_to', $helper->id);
        $this->postJson('/api/admin/support-tickets/' . $ticket . '/assign', [
            'assigned_to' => $helper->id,
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(403);

        // The assignment endpoint must enforce the same platform-staff
        // restriction as the assignable-staff directory.
        [$agencyMember] = $this->agencyUser(['agency.view'], 'agent', $agency);
        $this->postJson('/api/admin/support-tickets/' . $ticket . '/assign', [
            'assigned_to' => $agencyMember->id,
        ], $this->authHeaders($this->tokenFor($staff)))
            ->assertStatus(422);

        // A same-agency colleague may see + comment but not change status.
        [$colleague] = $this->agencyUser(['agency.view'], 'agent', $agency);
        $this->postJson('/api/support/tickets/' . $ticket . '/messages', [
            'body' => 'Same issue here, adding my notes.',
        ], $this->authHeaders($this->tokenFor($colleague)))
            ->assertStatus(201);
        $this->postJson('/api/support/tickets/' . $ticket . '/status', [
            'status' => 'open',
        ], $this->authHeaders($this->tokenFor($colleague)))
            ->assertStatus(403);

        // Creator can close their own ticket.
        $this->postJson('/api/support/tickets/' . $ticket . '/status', [
            'status' => 'closed',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->assertJsonPath('ticket.status', 'closed');
        $this->assertNotNull(SupportTicket::find($ticket)->closed_at);

        // Staff can still reopen after closure.
        $this->postJson('/api/support/tickets/' . $ticket . '/status', [
            'status' => 'open',
        ], $this->authHeaders($this->tokenFor($staff)))
            ->assertOk()
            ->assertJsonPath('ticket.status', 'open');
    }

    public function test_creator_cannot_use_ownership_to_bypass_another_agency_tenant(): void
    {
        [$user, $agency] = $this->memberUser();
        $otherAgency = $this->makeAgency('Different Agency');

        // Models a ticket that remains attached to a previous/other tenant
        // after the creator's active agency context has changed.
        $ticket = SupportTicket::create([
            'id' => 'tkt_' . bin2hex(random_bytes(8)),
            'agency_id' => $otherAgency->id,
            'user_id' => $user->id,
            'subject' => 'Historical agency ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->getJson('/api/support/tickets/' . $ticket->id, $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(404);
    }

    public function test_platform_side_account_gets_its_own_ticket_lane(): void
    {
        // No agency membership: a platform-side account.
        $solo = $this->makeUser('operator');
        $this->seedPlatformRole('operator');

        $ticket = $this->postJson('/api/support/tickets', [
            'subject' => 'Operator console access',
            'body' => 'Requesting access to the moderation console.',
        ], $this->authHeaders($this->tokenFor($solo)))
            ->assertStatus(201)
            ->json('ticket.id');

        $this->assertNull(SupportTicket::find($ticket)->agency_id);

        // Index shows only their own ticket, not other agencies'.
        $list = $this->getJson('/api/support/tickets', $this->authHeaders($this->tokenFor($solo)))->assertOk();
        $this->assertSame(1, count($list->json('tickets')));
        $this->assertSame($ticket, $list->json('tickets.0.id'));
    }
}
