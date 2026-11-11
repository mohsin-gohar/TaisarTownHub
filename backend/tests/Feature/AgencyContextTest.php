<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;

/**
 * Tenancy resolution (Phase 3 AgencyContext): the acting agency is derived
 * ONLY from the user's own active memberships — never from request bodies or
 * unverified headers.
 */
class AgencyContextTest extends EnterpriseTestCase
{
    public function test_member_defaults_to_first_active_membership(): void
    {
        $user = $this->makeUser('agent');
        $agency = $this->makeAgency('Member Agency');
        $role = $this->makeRole('agency', 'agent', ['agency.view']);
        $this->makeMember($user, $agency, $role);

        $this->getJson('/api/agency/profile', $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('agency.id', $agency->id);
    }

    public function test_member_cannot_request_another_agency(): void
    {
        $user = $this->makeUser('agent');
        $own = $this->makeAgency('Own Agency');
        $other = $this->makeAgency('Other Agency');
        $role = $this->makeRole('agency', 'agent', ['agency.view']);
        $this->makeMember($user, $own, $role);

        $this->getJson('/api/agency/profile', array_merge(
            $this->authHeaders($this->tokenFor($user)),
            ['X-Agency-Id' => $other->id]
        ))->assertStatus(403);
    }

    public function test_platform_user_without_manage_agencies_cannot_name_an_agency(): void
    {
        // Manager platform role does not hold admin.manage_agencies.
        $this->seedPlatformRole('manager');
        $user = $this->makeUser('manager');
        $this->assertFalse($user->hasPermission('admin.manage_agencies'));

        $agency = $this->makeAgency('Named Agency');

        $this->getJson('/api/agency/profile', array_merge(
            $this->authHeaders($this->tokenFor($user)),
            ['X-Agency-Id' => $agency->id]
        ))->assertStatus(403);
    }

    public function test_platform_admin_can_act_cross_agency(): void
    {
        $this->seedPlatformRole('super_admin');
        $user = $this->makeUser('super_admin');
        $this->assertTrue($user->hasPermission('admin.manage_agencies'));

        $agency = $this->makeAgency('Cross Agency');

        $this->getJson('/api/agency/profile', array_merge(
            $this->authHeaders($this->tokenFor($user)),
            ['X-Agency-Id' => $agency->id]
        ))->assertStatus(200)
            ->assertJsonPath('agency.id', $agency->id);
    }

    public function test_agency_scoped_listing_is_isolated_between_agencies(): void
    {
        $agencyA = $this->makeAgency('Agency A');
        $agencyB = $this->makeAgency('Agency B');
        $roleA = $this->makeRole('agency', 'agent', ['view_properties', 'create_properties', 'edit_properties', 'properties.view']);
        $roleB = $this->makeRole('agency', 'agent', ['view_properties', 'create_properties', 'edit_properties', 'properties.view']);

        $userA = $this->makeUser('agent');
        $userB = $this->makeUser('agent');
        $this->makeMember($userA, $agencyA, $roleA);
        $this->makeMember($userB, $agencyB, $roleB);

        // Seed one business record per agency through the workspace endpoint.
        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'br-a-1',
                'title' => 'Agency A listing',
                'agency_id' => $agencyA->id,
            ]],
        ]], $this->authHeaders($this->tokenFor($userA)))->assertStatus(200);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'br-b-1',
                'title' => 'Agency B listing',
                'agency_id' => $agencyB->id,
            ]],
        ]], $this->authHeaders($this->tokenFor($userB)))->assertStatus(200);

        $workspaceA = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200)
            ->json('properties');

        $titlesA = array_column($workspaceA, 'title');
        $this->assertContains('Agency A listing', $titlesA);
        $this->assertNotContains('Agency B listing', $titlesA);

        $workspaceB = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(200)
            ->json('properties');

        $titlesB = array_column($workspaceB, 'title');
        $this->assertContains('Agency B listing', $titlesB);
        $this->assertNotContains('Agency A listing', $titlesB);
    }

    public function test_platform_keys_cannot_be_granted_to_agency_roles(): void
    {
        $assignable = Permissions::agencyAssignable();
        foreach (Permissions::CATALOG as $key => $meta) {
            if (!empty($meta[2])) {
                $this->assertNotContains(
                    $key,
                    $assignable,
                    "platform permission {$key} must not be agency-assignable"
                );
            }
        }
    }
}
