<?php

namespace Tests\Feature;

use App\Models\MediaFile;

/**
 * Cross-agency platform operators must see the same capabilities the API
 * enforces. Capability flags in responses previously used the agency-scoped
 * permission resolution only, so a platform superadmin acting as an agency
 * was told they could not upload documents or edit media even though the
 * mutation endpoints accepted the request.
 *
 * These tests pin the shared AuthorizesApi::can() contract:
 *  - agency members stay least-privilege (no platform fallback);
 *  - cross-agency platform admins keep platform grants inside an agency;
 *  - agent-scoped endpoints (properties scope=agency) do not 403 them.
 */
class CrossAgencyCapabilitiesTest extends EnterpriseTestCase
{
    private function crossHeaders(string $token, string $agencyId): array
    {
        return array_merge($this->authHeaders($token), ['X-Agency-Id' => $agencyId]);
    }

    public function test_cross_agency_admin_receives_platform_capabilities(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $agency = $this->makeAgency('Capability Agency');

        $documents = $this->getJson(
            '/api/agency/documents',
            $this->crossHeaders($this->tokenFor($admin), $agency->id)
        )
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $this->assertTrue($documents['can_upload'], 'superadmin acting cross-agency may upload documents');
        $this->assertTrue($documents['can_see_sensitive'], 'superadmin acting cross-agency has sensitive clearance');

        $media = MediaFile::create([
            'id' => 'med_cap_' . bin2hex(random_bytes(4)),
            'agency_id' => $agency->id,
            'folder' => 'General',
            'title' => 'Capability Media',
            'type' => 'image',
            'visibility' => 'private',
            'size_bytes' => 10,
            'status' => 'available',
        ]);

        $listed = $this->getJson(
            '/api/agency/media',
            $this->crossHeaders($this->tokenFor($admin), $agency->id)
        )
            ->assertStatus(200)
            ->json('media');

        $this->assertCount(1, $listed);
        $this->assertTrue($listed[0]['can_edit'], 'superadmin acting cross-agency may manage media');
        $this->assertSame($media->id, $listed[0]['id']);

        // Agency-scoped listing view previously aborted 403 because the
        // capability check ignored the platform fallback.
        $this->getJson(
            '/api/properties?scope=agency',
            $this->crossHeaders($this->tokenFor($admin), $agency->id)
        )->assertStatus(200);
    }

    public function test_agency_member_does_not_inherit_platform_capabilities(): void
    {
        $agency = $this->makeAgency('Least Privilege Agency');
        [$agent] = $this->agencyUser([
            'agency.view',
            'documents.view',
            'properties.view',
        ], 'agent', $agency);

        $documents = $this->getJson(
            '/api/agency/documents',
            $this->crossHeaders($this->tokenFor($agent), $agency->id)
        )
            ->assertStatus(200)
            ->json();

        $this->assertFalse($documents['can_upload'], 'viewer must not be told they can upload');
        $this->assertFalse($documents['can_see_sensitive'], 'viewer must not see sensitive documents');

        // The mutation endpoint must agree with the capability flag.
        $this->postJson(
            '/api/agency/documents',
            [],
            $this->crossHeaders($this->tokenFor($agent), $agency->id)
        )->assertStatus(403);
    }

    public function test_cross_agency_admin_lead_pipeline_is_platform_wide(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $agency = $this->makeAgency('CRM Agency');

        $agentA = $this->makeUser('agent', ['name' => 'Agent A']);
        $agentB = $this->makeUser('agent', ['name' => 'Agent B']);
        $role = $this->makeRole('agency', 'agent', ['agency.view', 'crm.view']);
        $this->makeMember($agentA, $agency, $role);
        $this->makeMember($agentB, $agency, $role);

        \App\Models\Inquiry::create([
            'id' => 'inq_cap_' . bin2hex(random_bytes(4)),
            'fullName' => 'Lead A',
            'phone' => '03001234567',
            'message' => 'Looking for a plot',
            'agency_id' => $agency->id,
            'assigned_agent_id' => $agentA->id,
            'lead_status' => 'new',
        ]);
        \App\Models\Inquiry::create([
            'id' => 'inq_cap_' . bin2hex(random_bytes(4)),
            'fullName' => 'Lead B',
            'phone' => '03007654321',
            'message' => 'Looking for a house',
            'agency_id' => $agency->id,
            'assigned_agent_id' => $agentB->id,
            'lead_status' => 'contacted',
        ]);

        $leads = $this->getJson(
            '/api/agency/leads',
            $this->crossHeaders($this->tokenFor($admin), $agency->id)
        )
            ->assertStatus(200)
            ->json('leads');

        $this->assertCount(2, $leads, 'cross-agency admin sees every lead, not only self/unassigned');

        // The restricted agent still sees only their own lead.
        $agentLeads = $this->getJson(
            '/api/agency/leads',
            $this->crossHeaders($this->tokenFor($agentA), $agency->id)
        )
            ->assertStatus(200)
            ->json('leads');

        $this->assertCount(1, $agentLeads);
        $this->assertSame('Lead A', $agentLeads[0]['fullName']);
    }
}
