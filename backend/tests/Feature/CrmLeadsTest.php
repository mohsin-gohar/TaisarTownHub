<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Inquiry;
use Illuminate\Support\Str;

/**
 * CRM pipeline: public inquiry → agency attribution, record-level visibility,
 * assignment permissions, status transitions, and cross-agency isolation.
 */
class CrmLeadsTest extends EnterpriseTestCase
{
    private function createInquiry(Agency $agency, array $attrs = []): Inquiry
    {
        return Inquiry::create(array_merge([
            'id' => 'inq-' . Str::uuid(),
            'fullName' => 'Lead ' . Str::random(5),
            'email' => 'lead@example.test',
            'phone' => '03001234567',
            'subject' => 'Interested in a plot',
            'message' => 'Please call me.',
            'status' => 'Pending',
            'source' => 'Test',
            'agency_id' => $agency->id,
            'lead_status' => 'new',
        ], $attrs));
    }

    private function crmPermissions(bool $assign = false): array
    {
        $permissions = ['crm.view', 'crm.create_lead', 'crm.update_lead'];
        if ($assign) {
            $permissions[] = 'crm.assign_lead';
        }

        return $permissions;
    }

    public function test_public_inquiry_attaches_the_listing_agency_and_enters_the_pipeline(): void
    {
        [$manager, $agency] = $this->agencyUser($this->crmPermissions(true), 'manager');
        $property = $this->makePublicProperty($agency, ['title' => 'CRM Attribution Plot']);

        $this->asGuest();
        $this->postJson('/api/inquiries', [
            'fullName' => 'Prospective Buyer',
            'phone' => '03007654321',
            'message' => 'I want to visit this plot.',
            'propertyId' => $property->id,
        ])->assertStatus(201);

        $lead = Inquiry::where('phone', '03007654321')->first();
        $this->assertNotNull($lead);
        $this->assertEquals($agency->id, $lead->agency_id);
        $this->assertEquals($property->id, $lead->property_id);
        $this->assertEquals('new', $lead->lead_status);

        $this->getJson('/api/agency/leads', $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200)
            ->assertJsonPath('pipeline.new', 1);
    }

    public function test_public_inquiry_without_a_property_is_platform_owned(): void
    {
        $this->asGuest();
        $this->postJson('/api/inquiries', [
            'fullName' => 'General Visitor',
            'phone' => '03001112223',
            'message' => 'General question.',
        ])->assertStatus(201);

        $lead = Inquiry::where('phone', '03001112223')->first();
        $this->assertNull($lead->agency_id);
    }

    public function test_agent_without_assign_permission_sees_only_own_or_unassigned_leads(): void
    {
        [$agent, $agency] = $this->agencyUser($this->crmPermissions(false));
        [$peer] = $this->agencyUser($this->crmPermissions(false), 'agent', $agency);

        $mine = $this->createInquiry($agency, ['fullName' => 'Mine', 'assigned_agent_id' => $agent->id]);
        $unassigned = $this->createInquiry($agency, ['fullName' => 'Unassigned']);
        $foreign = $this->createInquiry($agency, ['fullName' => 'Peer Only', 'assigned_agent_id' => $peer->id]);

        $leads = $this->getJson('/api/agency/leads', $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(200)
            ->json('leads');

        $names = array_column($leads, 'fullName');
        $this->assertContains('Mine', $names);
        $this->assertContains('Unassigned', $names);
        $this->assertNotContains('Peer Only', $names);

        // Direct record fetch of someone else's lead is a 404.
        $this->getJson("/api/agency/leads/{$foreign->id}", $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(404);
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        [$manager, $agency] = $this->agencyUser([...$this->crmPermissions(true), 'crm.export'], 'manager');
        $payload = '=HYPERLINK("http://evil.test","click")';
        $this->createInquiry($agency, [
            'fullName' => $payload,
            'adminNotes' => '+SUM(1+1)',
        ]);

        $response = $this->getJson('/api/agency/leads/export', $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200);
        $csv = $response->getContent();

        $this->assertStringContainsString('"\'=HYPERLINK', $csv);
        $this->assertStringContainsString("'+SUM(1+1)", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_agent_cannot_mutate_a_colleagues_inquiry_through_legacy_endpoints(): void
    {
        [$agent, $agency] = $this->agencyUser($this->crmPermissions(false));
        [$peer] = $this->agencyUser($this->crmPermissions(false), 'agent', $agency);
        $lead = $this->createInquiry($agency, ['fullName' => 'Peer Lead', 'assigned_agent_id' => $peer->id]);
        $headers = $this->authHeaders($this->tokenFor($agent));

        $this->patchJson("/api/inquiries/{$lead->id}/status", ['status' => 'Resolved'], $headers)->assertStatus(404);
        $this->patchJson("/api/inquiries/{$lead->id}/notes", ['notes' => 'tampered'], $headers)->assertStatus(404);
        // Deletion additionally requires crm.delete_lead; the permission gate
        // must still run before any record lookup.
        $this->deleteJson("/api/inquiries/{$lead->id}", [], $headers)->assertStatus(403);

        $this->assertSame('Pending', $lead->fresh()->status);
        $this->assertNotNull(Inquiry::find($lead->id));
    }

    public function test_assignment_requires_crm_assign_permission(): void
    {
        [$agent, $agency] = $this->agencyUser($this->crmPermissions(false));
        [$manager] = $this->agencyUser($this->crmPermissions(true), 'manager', $agency);
        $lead = $this->createInquiry($agency);

        $this->postJson("/api/agency/leads/{$lead->id}/assign", ['agent_id' => $agent->id], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(403);

        $this->postJson("/api/agency/leads/{$lead->id}/assign", ['agent_id' => $agent->id], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($agent->id, $lead->fresh()->assigned_agent_id);
        $this->assertDatabaseHas('lead_activities', [
            'inquiry_id' => $lead->id,
            'type' => 'assignment',
        ]);
    }

    public function test_assignment_rejects_users_outside_the_agency(): void
    {
        [$manager, $agency] = $this->agencyUser($this->crmPermissions(true), 'manager');
        [$outsider] = $this->agencyUser($this->crmPermissions(false));
        $lead = $this->createInquiry($agency);

        $this->postJson("/api/agency/leads/{$lead->id}/assign", ['agent_id' => $outsider->id], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($lead->fresh()->assigned_agent_id);
    }

    public function test_lead_status_transitions_are_validated_and_audited(): void
    {
        [$manager, $agency] = $this->agencyUser($this->crmPermissions(true), 'manager');
        $lead = $this->createInquiry($agency);

        $this->patchJson("/api/agency/leads/{$lead->id}", [
            'lead_status' => 'contacted',
            'follow_up_at' => now()->addDay()->toDateTimeString(),
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200)
            ->assertJsonPath('lead.lead_status', 'contacted');

        $this->patchJson("/api/agency/leads/{$lead->id}", ['lead_status' => 'not-a-status'], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lead_status');

        $this->assertEquals('contacted', $lead->fresh()->lead_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.status', 'entity_id' => $lead->id]);

        // Conversion clears the follow-up and stamps converted_at.
        $this->patchJson("/api/agency/leads/{$lead->id}", ['lead_status' => 'converted'], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200);
        $this->assertNotNull($lead->fresh()->converted_at);
        $this->assertNull($lead->fresh()->follow_up_at);
    }

    public function test_agencies_cannot_see_each_others_leads(): void
    {
        [$managerA] = $this->agencyUser($this->crmPermissions(true), 'manager');
        [, $agencyB] = $this->agencyUser($this->crmPermissions(true), 'manager');
        $foreignLead = $this->createInquiry($agencyB, ['fullName' => 'Foreign Lead']);

        $this->getJson("/api/agency/leads/{$foreignLead->id}", $this->authHeaders($this->tokenFor($managerA)))
            ->assertStatus(404);

        $leads = $this->getJson('/api/agency/leads', $this->authHeaders($this->tokenFor($managerA)))
            ->assertStatus(200)
            ->json('leads');
        $this->assertNotContains('Foreign Lead', array_column($leads, 'fullName'));
    }

    public function test_manual_lead_creation_is_agency_scoped(): void
    {
        [$manager, $agency] = $this->agencyUser($this->crmPermissions(true), 'manager');

        $this->postJson('/api/agency/leads', [
            'fullName' => 'Walk-in Client',
            'phone' => '03005556667',
            'message' => 'Came to the office.',
            'source' => 'Walk-in',
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(201)
            ->assertJsonPath('lead.agency_id', $agency->id)
            ->assertJsonPath('lead.lead_status', 'new');
    }
}
