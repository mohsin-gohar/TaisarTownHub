<?php

namespace Tests\\Feature;

use App\\Models\\AgencyMember;

/**
 * Phase 3.9 — agency member and role authorization boundaries.
 */
class AgencyMemberAccessRestrictionsTest extends EnterpriseTestCase
{
    public function test_member_directory_and_roles_are_limited_to_the_acting_agency(): void
    {
        $agencyA = $this->makeAgency('Staff Agency A');
        $agencyB = $this->makeAgency('Staff Agency B');
        [$viewerA] = $this->agencyUser(['agency.view'], 'agent', $agencyA);
        $roleB = $this->makeRole('agency', 'agency_b_private_role', ['agency.view']);
        $roleB->update(['agency_id' => $agencyB->id]);
        $memberB = $this->makeUser('agent');
        $this->makeMember($memberB, $agencyB, $roleB);

        $members = $this->getJson('/api/agency/members', $this->authHeaders($this->tokenFor($viewerA)))
            ->assertOk()->json('members');
        $this->assertNotContains($memberB->id, array_column($members, 'user_id'));

        $roles = $this->getJson('/api/agency/roles', $this->authHeaders($this->tokenFor($viewerA)))
            ->assertOk()->json('roles');
        $this->assertNotContains($roleB->id, array_column($roles, 'id'));
    }

    public function test_read_only_member_cannot_invite_change_or_remove_staff_or_create_roles(): void
    {
        $agency = $this->makeAgency('Read Only Staff Agency');
        [$viewer] = $this->agencyUser(['agency.view'], 'agent', $agency);
        $targetRole = $this->makeRole('agency', 'agent', ['agency.view']);
        $target = $this->makeUser('agent');
        $membership = $this->makeMember($target, $agency, $targetRole);

        $headers = $this->authHeaders($this->tokenFor($viewer));

        $this->postJson('/api/agency/members/invite', [
            'email' => 'new.staff@example.test',
            'role_id' => $targetRole->id,
        ], $headers)->assertForbidden();

        $this->patchJson('/api/agency/members/'.$membership->id, [
            'status' => 'deactivated',
        ], $headers)->assertForbidden();

        $this->deleteJson('/api/agency/members/'.$membership->id, [], $headers)
            ->assertForbidden();

        $this->postJson('/api/agency/roles', [
            'name' => 'Restricted role',
            'key' => 'restricted_role',
            'permissions' => ['agency.view'],
        ], $headers)->assertForbidden();

        $this->assertDatabaseHas('agency_members', [
            'id' => $membership->id,
            'status' => 'active',
        ]);
    }

    public function test_agency_manager_cannot_change_or_remove_another_agencys_member(): void
    {
        $agencyA = $this->makeAgency('Manager Agency A');
        $agencyB = $this->makeAgency('Member Agency B');
        [$managerA] = $this->agencyUser(['agency.view', 'agency.manage_staff'], 'agency_manager', $agencyA);
        [$targetB] = $this->agencyUser(['agency.view'], 'agent', $agencyB);

        $membershipB = AgencyMember::where('agency_id', $agencyB->id)
            ->where('user_id', $targetB->id)->firstOrFail();

        $headers = $this->authHeaders($this->tokenFor($managerA));

        $this->patchJson('/api/agency/members/'.$membershipB->id, [
            'status' => 'deactivated',
        ], $headers)->assertNotFound();

        $this->deleteJson('/api/agency/members/'.$membershipB->id, [], $headers)
            ->assertNotFound();

        $this->assertDatabaseHas('agency_members', [
            'id' => $membershipB->id,
            'agency_id' => $agencyB->id,
            'status' => 'active',
        ]);
    }

    public function test_agency_manager_cannot_assign_a_role_owned_by_another_agency(): void
    {
        $agencyA = $this->makeAgency('Role Owner A');
        $agencyB = $this->makeAgency('Role Owner B');
        [$managerA] = $this->agencyUser(['agency.view', 'agency.manage_staff'], 'agency_manager', $agencyA);
        [$target] = $this->agencyUser(['agency.view'], 'agent', $agencyA);
        $targetMembership = AgencyMember::where('agency_id', $agencyA->id)
            ->where('user_id', $target->id)->firstOrFail();

        $foreignRole = $this->makeRole('agency', 'private_custom_role', ['agency.view']);
        $foreignRole->update(['agency_id' => $agencyB->id]);

        $this->patchJson('/api/agency/members/'.$targetMembership->id, [
            'role_id' => $foreignRole->id,
        ], $this->authHeaders($this->tokenFor($managerA)))->assertStatus(422);

        $this->assertDatabaseHas('agency_members', [
            'id' => $targetMembership->id,
            'role_id' => $targetMembership->role_id,
        ]);
    }

    public function test_agency_member_cannot_request_another_tenants_staff_directory(): void
    {
        $agencyA = $this->makeAgency('Header Agency A');
        $agencyB = $this->makeAgency('Header Agency B');
        [$memberA] = $this->agencyUser(['agency.view'], 'agent', $agencyA);

        $this->getJson('/api/agency/members', array_merge(
            $this->authHeaders($this->tokenFor($memberA)),
            ['X-Agency-Id' => $agencyB->id]
        ))->assertForbidden();
    }
}
