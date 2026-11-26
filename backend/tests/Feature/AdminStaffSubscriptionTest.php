<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Platform admin surfaces, agency staff management and subscription scoping.
 *
 * Security invariants covered here:
 *  - platform-only endpoints reject agency-scoped members;
 *  - agency roles can never be granted platform permissions;
 *  - member/role lists are scoped to the acting agency;
 *  - invitations respect subscription staff limits;
 *  - dashboards split platform-wide vs agency-scoped data.
 */
class AdminStaffSubscriptionTest extends EnterpriseTestCase
{
    private function staffPermissions(): array
    {
        return ['agency.view', 'agency.manage_staff', 'view_properties'];
    }

    private function makePlan(array $attrs = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'key' => 'growth-' . substr(md5(uniqid('', true)), 0, 6),
            'name' => 'Growth',
            'description' => 'Test plan',
            'price' => 5000,
            'currency' => 'PKR',
            'interval' => 'month',
            'limits' => ['listings' => 25, 'staff' => 5, 'storage_mb' => 2048, 'leads' => 100],
            'features' => ['priority_support'],
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ], $attrs));
    }

    public function test_platform_admin_can_manage_users_with_self_protection(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin', ['email' => 'platform-admin@example.test']);

        $created = $this->postJson('/api/admin/users', [
            'name' => 'New Operator',
            'email' => 'new-operator@example.test',
            'password' => 'TempPass123!',
            'role' => 'operator',
        ], $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'operator')
            ->json('user');

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.create', 'entity_id' => (string) $created['id']]);

        $list = $this->getJson('/api/admin/users', $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json('users');
        $this->assertContains('new-operator@example.test', array_column($list, 'email'));

        // Suspending another account revokes its sessions.
        $target = User::where('email', 'new-operator@example.test')->firstOrFail();
        $target->createToken('target-session');

        $this->patchJson("/api/admin/users/{$target->id}", ['status' => 'suspended'], $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonPath('user.status', 'suspended');
        $this->assertEquals(0, $target->tokens()->count(), 'suspension revokes every token of the target account');

        // An admin can never demote or suspend their own account.
        $this->patchJson("/api/admin/users/{$admin->id}", ['status' => 'suspended'], $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->patchJson("/api/admin/users/{$admin->id}", ['role' => 'operator'], $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(422);
    }

    public function test_subscription_plan_prices_are_whole_pkr_only(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $headers = $this->authHeaders($this->tokenFor($admin));
        $payload = [
            'key' => 'precision-' . substr(md5(uniqid('', true)), 0, 8),
            'name' => 'Precision Plan',
            'price' => 4999.50,
            'currency' => 'PKR',
            'interval' => 'month',
        ];

        $this->postJson('/api/admin/plans', $payload, $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');

        $payload['key'] = 'currency-' . substr(md5(uniqid('', true)), 0, 8);
        $payload['price'] = 4999;
        $payload['currency'] = 'USD';
        $this->postJson('/api/admin/plans', $payload, $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');

        $plan = $this->makePlan();
        $this->patchJson("/api/admin/plans/{$plan->id}", ['price' => 4999.50], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
        $this->patchJson("/api/admin/plans/{$plan->id}", ['currency' => 'USD'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_platform_surfaces_reject_agency_members(): void
    {
        [$agent] = $this->agencyUser($this->staffPermissions());

        $this->getJson('/api/admin/users', $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);
        $this->postJson('/api/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'SneakyPass123!',
            'role' => 'admin',
        ], $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);
        $this->getJson('/api/admin/settings', $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);
        $this->getJson('/api/admin/agencies', $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);
        $this->getJson('/api/admin/plans', $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);
        $this->getJson('/api/admin/audit-logs', $this->authHeaders($this->tokenFor($agent)))->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.test']);
    }

    public function test_platform_admin_can_read_and_update_platform_settings(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');

        DB::table('system_settings')->insert([
            'key' => 'test.banner',
            'value' => json_encode('Old banner'),
            'group' => 'general',
            'description' => 'Test setting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settings = $this->getJson('/api/admin/settings', $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json('settings');
        $this->assertContains('test.banner', array_column($settings, 'key'));

        $this->patchJson('/api/admin/settings', [
            'key' => 'test.banner',
            'value' => 'New banner',
        ], $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonPath('setting.value', 'New banner');

        $this->assertDatabaseHas('audit_logs', ['action' => 'setting.update']);
    }

    public function test_agency_manager_cannot_escalate_permissions_through_custom_roles_or_self_grants(): void
    {
        [$manager, $agency] = $this->agencyUser([
            'agency.view',
            'agency.manage_staff',
        ], 'agency_manager');

        $privilegedRole = $this->makeRole('agency', 'billing_admin', [
            'agency.view',
            'agency.manage_staff',
            'agency.subscribe',
            'agency.manage_settings',
        ]);
        $privilegedRole->forceFill(['agency_id' => $agency->id])->save();
        $memberId = $manager->memberships()->where('agency_id', $agency->id)->value('id');

        $this->postJson('/api/agency/roles', [
            'name' => 'Self Billing Admin',
            'key' => 'self_billing_admin',
            'permissions' => ['agency.view', 'agency.subscribe'],
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertForbidden();

        $this->putJson('/api/agency/roles/' . $privilegedRole->id, [
            'permissions' => ['agency.view', 'agency.subscribe'],
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertForbidden();

        $this->patchJson('/api/agency/members/' . $memberId, [
            'role_id' => $privilegedRole->id,
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertForbidden();

        $this->patchJson('/api/agency/members/' . $memberId, [
            'permissions' => ['agency.subscribe'],
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertForbidden();

        $this->assertFalse($manager->fresh()->hasPermission('agency.subscribe', $agency->id));
        $this->assertFalse($manager->fresh()->hasPermission('agency.manage_settings', $agency->id));
    }

    public function test_agency_roles_cannot_grant_platform_permissions(): void
    {
        [$manager] = $this->agencyUser($this->staffPermissions(), 'manager');

        $this->postJson('/api/agency/roles', [
            'name' => 'Escalated Role',
            'key' => 'escalated_role',
            'permissions' => ['admin.manage_users'],
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Platform-level permissions cannot be granted to agency roles: admin.manage_users']);

        $this->assertDatabaseMissing('roles', ['key' => 'escalated_role']);

        // A legitimate agency permission is accepted.
        $this->postJson('/api/agency/roles', [
            'name' => 'Senior Agent',
            'key' => 'senior_agent',
            'permissions' => ['properties.view', 'crm.view'],
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $catalog = $this->getJson('/api/agency/roles', $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(200)
            ->json('assignable_permissions');
        $this->assertNotContains('admin.manage_users', $catalog);
        $this->assertContains('properties.view', $catalog);
    }

    public function test_members_and_roles_are_scoped_to_the_acting_agency(): void
    {
        [$managerA, $agencyA] = $this->agencyUser($this->staffPermissions(), 'manager');
        [$agentA] = $this->agencyUser(['properties.view'], 'agent', $agencyA);
        [$managerB] = $this->agencyUser($this->staffPermissions(), 'manager');

        $this->postJson('/api/agency/roles', [
            'name' => 'A Only',
            'key' => 'a_only_role',
            'permissions' => ['properties.view'],
        ], $this->authHeaders($this->tokenFor($managerA)))->assertStatus(201);

        $membersA = $this->getJson('/api/agency/members', $this->authHeaders($this->tokenFor($managerA)))
            ->assertStatus(200)
            ->json('members');
        $this->assertEqualsCanonicalizing(
            [$managerA->id, $agentA->id],
            array_column($membersA, 'user_id')
        );

        $membersB = $this->getJson('/api/agency/members', $this->authHeaders($this->tokenFor($managerB)))
            ->assertStatus(200)
            ->json('members');
        $this->assertNotContains($managerA->id, array_column($membersB, 'user_id'));

        $roleKeysB = array_column(
            $this->getJson('/api/agency/roles', $this->authHeaders($this->tokenFor($managerB)))->json('roles'),
            'key'
        );
        $this->assertNotContains('a_only_role', $roleKeysB);
    }

    public function test_invitation_flow_adds_a_new_user_to_the_agency(): void
    {
        [$manager, $agency] = $this->agencyUser($this->staffPermissions(), 'manager');
        $role = $this->makeRole('agency', 'field_agent', ['properties.view']);

        $invite = $this->postJson('/api/agency/members/invite', [
            'email' => 'invitee@example.test',
            'role_id' => $role->id,
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->json('invitation');

        $this->assertNotEmpty($invite['invite_token']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.invite']);

        $this->asGuest();
        $accepted = $this->postJson('/api/agency/invitations/accept', [
            'token' => $invite['invite_token'],
            'name' => 'Invited Agent',
            'password' => 'InvitedPass123!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $newUser = User::where('email', 'invitee@example.test')->firstOrFail();
        $this->assertSame($agency->id, $newUser->memberships()->first()->agency_id);
        $this->assertEquals('accepted', DB::table('agency_invitations')->where('email', 'invitee@example.test')->value('status'));

        // The issued token belongs to the invited user.
        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($accepted['token']))
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'invitee@example.test');
    }

    public function test_owner_invitation_does_not_grant_platform_privileges(): void
    {
        // Production seeds a platform 'admin' role row that grants
        // admin.manage_agencies. Without it a buggy role mapping can pass
        // silently in tests while escalating in production.
        $this->seedPlatformRole('admin');
        $ownerRole = $this->makeRole('agency', 'agency_owner', array_merge(
            $this->staffPermissions(),
            ['agency.view', 'agency.manage_staff']
        ));
        $ownerRole->forceFill(['id' => 'role_agency_owner'])->save();

        [$owner, $agency] = $this->agencyUser($this->staffPermissions(), 'manager');
        $owner->memberships()->where('agency_id', $agency->id)->firstOrFail()
            ->update(['role_id' => $ownerRole->id]);

        $invite = $this->postJson('/api/agency/members/invite', [
            'email' => 'invited-owner@example.test',
            'role_id' => 'role_agency_owner',
        ], $this->authHeaders($this->tokenFor($owner)))
            ->assertStatus(201)
            ->json('invitation');
        $this->assertNotEmpty($invite['invite_token']);

        $this->asGuest();
        $accepted = $this->postJson('/api/agency/invitations/accept', [
            'token' => $invite['invite_token'],
            'name' => 'Invited Owner',
            'password' => 'OwnerPass123!',
        ])->assertStatus(200)->json();

        $invitee = User::where('email', 'invited-owner@example.test')->firstOrFail();
        $this->assertSame('dealer', $invitee->role, 'agency roles must never write a platform role');
        $this->assertFalse($invitee->isPlatformUser());
        $this->assertFalse($accepted['user']['is_platform']);
        $this->assertSame('agency_owner', $invitee->memberships()->first()->role->key);

        $headers = $this->authHeaders($accepted['token']);
        $this->getJson('/api/admin/agencies', $headers)->assertForbidden();
        $this->getJson('/api/admin/plans', $headers)->assertForbidden();
        $this->getJson('/api/admin/audit-logs', $headers)->assertForbidden();
        $this->getJson('/api/agency/members', $headers)->assertOk();
    }

    public function test_only_an_agency_owner_can_remove_an_owner_and_last_owner_is_protected(): void
    {
        $ownerRole = $this->makeRole('agency', 'agency_owner', $this->staffPermissions());
        $ownerRole->forceFill(['id' => 'role_agency_owner'])->save();

        [$owner, $agency] = $this->agencyUser($this->staffPermissions(), 'manager');
        $ownerMembership = $owner->memberships()->where('agency_id', $agency->id)->firstOrFail();
        $ownerMembership->update(['role_id' => $ownerRole->id]);

        [$manager] = $this->agencyUser($this->staffPermissions(), 'manager', $agency);

        $this->patchJson('/api/agency/members/' . $ownerMembership->id, [
            'status' => 'deactivated',
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only an agency owner may deactivate an owner membership.');

        $this->deleteJson('/api/agency/members/' . $ownerMembership->id, [], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only an agency owner may remove an owner membership.');

        $this->patchJson('/api/agency/members/' . $ownerMembership->id, [
            'status' => 'deactivated',
        ], $this->authHeaders($this->tokenFor($owner)))
            ->assertStatus(422)
            ->assertJsonPath('message', 'An agency must keep at least one active owner.');
    }

    public function test_agency_manager_cannot_invite_another_owner(): void
    {
        $ownerRole = $this->makeRole('agency', 'agency_owner', $this->staffPermissions());
        $ownerRole->forceFill(['id' => 'role_agency_owner'])->save();
        [$manager] = $this->agencyUser($this->staffPermissions(), 'manager');

        $this->postJson('/api/agency/members/invite', [
            'email' => 'owner-by-manager@example.test',
            'role_id' => 'role_agency_owner',
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('agency_invitations', ['email' => 'owner-by-manager@example.test']);
    }

    public function test_invitation_token_cannot_be_accepted_twice(): void
    {
        [$manager] = $this->agencyUser($this->staffPermissions(), 'manager');
        $role = $this->makeRole('agency', 'single_use', ['properties.view']);

        $invite = $this->postJson('/api/agency/members/invite', [
            'email' => 'single-use@example.test',
            'role_id' => $role->id,
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(201)
            ->json('invitation');

        $this->asGuest();
        $this->postJson('/api/agency/invitations/accept', [
            'token' => $invite['invite_token'],
            'name' => 'Single Use',
            'password' => 'SinglePass123!',
        ])->assertStatus(200);

        $this->asGuest();
        $this->postJson('/api/agency/invitations/accept', [
            'token' => $invite['invite_token'],
            'name' => 'Single Use Two',
            'password' => 'SinglePass123!',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(1, DB::table('agency_invitations')
            ->where('email', 'single-use@example.test')
            ->where('status', 'accepted')
            ->count());
    }

    public function test_invitations_respect_the_subscription_staff_limit(): void
    {
        [$manager, $agency] = $this->agencyUser($this->staffPermissions(), 'manager');
        $plan = $this->makePlan(['limits' => ['staff' => 1, 'listings' => 10]]);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $role = $this->makeRole('agency', 'agent_limit', ['properties.view']);

        $this->postJson('/api/agency/members/invite', [
            'email' => 'over-limit@example.test',
            'role_id' => $role->id,
        ], $this->authHeaders($this->tokenFor($manager)))
            ->assertStatus(402)
            ->assertJsonPath('success', false);
    }

    public function test_plan_catalog_is_public_and_subscriptions_are_agency_scoped(): void
    {
        $plan = $this->makePlan(['key' => 'starter-test', 'name' => 'Starter']);

        $this->asGuest();
        $plans = $this->getJson('/api/plans')->assertStatus(200)->json('plans');
        $this->assertContains('starter-test', array_column($plans, 'key'));

        [$memberA, $agencyA] = $this->agencyUser(['agency.view'], 'manager');
        [$memberB] = $this->agencyUser(['agency.view'], 'manager');

        AgencySubscription::create([
            'agency_id' => $agencyA->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $subA = $this->getJson('/api/agency/subscription', $this->authHeaders($this->tokenFor($memberA)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.key', 'starter-test')
            ->assertJsonPath('limits.staff', 5)
            ->json();

        $this->assertEquals(1, $subA['usage']['members']);
        $this->assertEquals(4, $subA['remaining']['staff']);

        $subB = $this->getJson('/api/agency/subscription', $this->authHeaders($this->tokenFor($memberB)))
            ->assertStatus(200)
            ->assertJsonPath('subscription.status', 'none')
            ->json();
        $this->assertContains('starter-test', array_column($subB['available_plans'], 'key'));
    }

    public function test_dashboard_separates_platform_wide_and_agency_scoped_data(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');

        [$agentA, $agencyA] = $this->agencyUser(['view_properties', 'agency.view']);
        [, $agencyB] = $this->agencyUser(['view_properties', 'agency.view']);

        $this->makePublicProperty($agencyA, ['title' => 'Agency A Listing']);
        $this->makePublicProperty($agencyB, ['title' => 'Agency B Draft', 'approval_status' => 'draft', 'visibility' => 'private']);

        $platform = $this->getJson('/api/admin/dashboard', $this->authHeaders($this->tokenFor($admin)))
            ->assertStatus(200)
            ->assertJsonPath('scope', 'platform')
            ->assertJsonPath('listings.total', 2)
            ->json();
        $this->assertArrayHasKey('platform', $platform);
        $this->assertArrayHasKey('users', $platform['platform']);

        $scoped = $this->getJson('/api/admin/dashboard', $this->authHeaders($this->tokenFor($agentA)))
            ->assertStatus(200)
            ->assertJsonPath('scope', $agencyA->id)
            ->assertJsonPath('listings.total', 1)
            ->json();
        $this->assertSame([], $scoped['platform']);
    }
}
