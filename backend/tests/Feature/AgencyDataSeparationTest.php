<?php

namespace Tests\\Feature;

use App\\Models\\BusinessRecord;

/**
 * Phase 3.8 — adversarial two-tenant isolation tests.
 *
 * Both agencies use the same workspace API and permissions. A tenant must
 * only read, update, or replace its own rows; client-supplied ownership fields
 * and guessed foreign record IDs must not cross the boundary.
 */
class AgencyDataSeparationTest extends EnterpriseTestCase
{
    private function seedWorkspaceRow(string $id, string $agencyId, string $title): void
    {
        BusinessRecord::put('properties', $id, ['id' => $id, 'title' => $title]);
        BusinessRecord::where('collection', 'properties')
            ->where('record_id', $id)
            ->update(['agency_id' => $agencyId]);
    }

    private function workspaceHeaders($user): array
    {
        return $this->authHeaders($this->tokenFor($user));
    }

    public function test_agency_a_and_b_only_read_their_own_workspace_records(): void
    {
        $agencyA = $this->makeAgency('Agency A');
        $agencyB = $this->makeAgency('Agency B');
        [$userA] = $this->agencyUser(['properties.view', 'properties.create', 'properties.update'], 'agent', $agencyA);
        [$userB] = $this->agencyUser(['properties.view', 'properties.create', 'properties.update'], 'agent', $agencyB);

        $this->seedWorkspaceRow('agency-a-property', $agencyA->id, 'A private listing');
        $this->seedWorkspaceRow('agency-b-property', $agencyB->id, 'B private listing');

        $rowsA = $this->getJson('/api/workspace', $this->workspaceHeaders($userA))
            ->assertOk()->json('properties');
        $rowsB = $this->getJson('/api/workspace', $this->workspaceHeaders($userB))
            ->assertOk()->json('properties');

        $idsA = array_column($rowsA, 'id');
        $idsB = array_column($rowsB, 'id');

        $this->assertContains('agency-a-property', $idsA);
        $this->assertNotContains('agency-b-property', $idsA);
        $this->assertContains('agency-b-property', $idsB);
        $this->assertNotContains('agency-a-property', $idsB);
    }

    public function test_agency_a_cannot_overwrite_or_reassign_agency_b_record_by_guessing_its_id(): void
    {
        $agencyA = $this->makeAgency('Agency A');
        $agencyB = $this->makeAgency('Agency B');
        [$userA] = $this->agencyUser(['properties.view', 'properties.create', 'properties.update'], 'agent', $agencyA);

        $this->seedWorkspaceRow('foreign-property', $agencyB->id, 'B original title');

        $this->putJson('/api/workspace', [
            'collections' => [
                'properties' => [[
                    'id' => 'foreign-property',
                    'title' => 'A attempted takeover',
                    'agency_id' => $agencyA->id,
                ]],
            ],
        ], $this->workspaceHeaders($userA))->assertOk();

        $foreign = BusinessRecord::where('collection', 'properties')
            ->where('record_id', 'foreign-property')->firstOrFail();

        $this->assertSame($agencyB->id, $foreign->agency_id);
        $this->assertSame('B original title', $foreign->payload['title']);
    }

    public function test_replace_mode_from_agency_a_never_deletes_agency_b_rows(): void
    {
        $agencyA = $this->makeAgency('Agency A');
        $agencyB = $this->makeAgency('Agency B');
        [$userA] = $this->agencyUser(['properties.view', 'properties.create', 'properties.update', 'properties.delete'], 'agency_owner', $agencyA);

        $this->seedWorkspaceRow('a-old-property', $agencyA->id, 'A old');
        $this->seedWorkspaceRow('b-kept-property', $agencyB->id, 'B must survive');

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'a-new-property',
                'title' => 'A replacement',
            ]]],
            'mode' => 'replace',
        ], $this->workspaceHeaders($userA))->assertOk();

        $this->assertNotNull(BusinessRecord::where('collection', 'properties')
            ->where('record_id', 'a-old-property')->value('deleted_at'));
        $this->assertNull(BusinessRecord::where('collection', 'properties')
            ->where('record_id', 'b-kept-property')->value('deleted_at'));
        $this->assertSame($agencyB->id, BusinessRecord::where('collection', 'properties')
            ->where('record_id', 'b-kept-property')->value('agency_id'));
    }

    public function test_agency_a_cannot_select_agency_b_through_tenant_header(): void
    {
        $agencyA = $this->makeAgency('Agency A');
        $agencyB = $this->makeAgency('Agency B');
        [$userA] = $this->agencyUser(['agency.view', 'properties.view'], 'agent', $agencyA);

        $this->getJson('/api/agency/profile', array_merge(
            $this->workspaceHeaders($userA),
            ['X-Agency-Id' => $agencyB->id]
        ))->assertForbidden();
    }
}
