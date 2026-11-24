<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Support\Permissions;

/**
 * Least-privilege checks for the legacy workspace sync surface: a read-only
 * role must not be able to write, and standard agency-owner grants must not
 * silently acquire platform-managed collections (dealers/projects/testimonials).
 */
class WorkspaceRoleGateTest extends EnterpriseTestCase
{
    public function test_read_only_viewer_cannot_write_inquiries_or_subscribers(): void
    {
        [$user] = $this->agencyUser(Permissions::agencyRoleGrants('viewer'), 'viewer');

        $response = $this->putJson('/api/workspace', [
            'collections' => [
                'properties' => [['id' => 'viewer-prop', 'title' => 'Nope']],
                'inquiries' => [['id' => 'viewer-lead', 'title' => 'Nope']],
                'subscribers' => [['id' => 'viewer-sub', 'email' => 'nope@example.test']],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertContains('properties', $response->json('denied'));
        $this->assertContains('inquiries', $response->json('denied'));
        $this->assertContains('subscribers', $response->json('denied'));
        $this->assertSame(0, BusinessRecord::count(), 'a read-only viewer must persist nothing');
    }

    public function test_agency_owner_grants_do_not_include_platform_managed_collections(): void
    {
        [$user] = $this->agencyUser(Permissions::agencyRoleGrants('agency_owner'), 'agency_owner');

        $response = $this->putJson('/api/workspace', [
            'collections' => [
                'properties' => [['id' => 'owner-prop', 'title' => 'Allowed']],
                'sellers' => [['id' => 'owner-seller', 'title' => 'Allowed']],
                'dealers' => [['id' => 'owner-dealer', 'title' => 'Denied']],
                'projects' => [['id' => 'owner-project', 'title' => 'Denied']],
                'testimonials' => [['id' => 'owner-testimonial', 'title' => 'Denied']],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame(1, $response->json('written.properties'));
        $this->assertSame(1, $response->json('written.sellers'));
        $this->assertContains('dealers', $response->json('denied'));
        $this->assertContains('projects', $response->json('denied'));
        $this->assertContains('testimonials', $response->json('denied'));

        $this->assertDatabaseHas('business_records', ['collection' => 'properties', 'record_id' => 'owner-prop']);
        $this->assertDatabaseHas('business_records', ['collection' => 'sellers', 'record_id' => 'owner-seller']);
        $this->assertDatabaseMissing('business_records', ['collection' => 'dealers', 'record_id' => 'owner-dealer']);
        $this->assertDatabaseMissing('business_records', ['collection' => 'projects', 'record_id' => 'owner-project']);
        $this->assertDatabaseMissing('business_records', ['collection' => 'testimonials', 'record_id' => 'owner-testimonial']);
    }

    public function test_agency_owner_grants_include_the_legacy_aliases_required_by_the_admin_ui(): void
    {
        $grants = Permissions::agencyRoleGrants('agency_owner');

        foreach ([
            'legacy.view_properties',
            'legacy.create_properties',
            'legacy.edit_properties',
            'legacy.delete_properties',
            'legacy.upload_documents',
            'legacy.verify_documents',
            'legacy.view_sensitive_cnic',
            'legacy.view_transactions',
            'legacy.create_transactions',
            'legacy.update_transactions',
            'legacy.cancel_transactions',
            'legacy.void_transactions',
            'legacy.manage_payments',
            'legacy.view_reports',
        ] as $permission) {
            $this->assertContains($permission, $grants, "agency_owner is missing legacy alias [{$permission}]");
        }

        $this->assertNotContains('edit_cms', $grants, 'global website CMS must remain platform-only');
        $this->assertNotContains('manage_users', $grants, 'platform user management must remain platform-only');
        $this->assertNotContains('admin.manage_agencies', $grants, 'agency verification must remain platform-only');
    }

    public function test_agency_owner_membership_resolves_legacy_ui_permissions_without_platform_scope(): void
    {
        [$user, $agency] = $this->agencyUser(Permissions::agencyRoleGrants('agency_owner'), 'agency_owner');
        $user->forceFill(['role' => 'dealer'])->save();

        $permissions = $user->permissionsFor($agency->id);

        foreach ([
            'create_properties',
            'edit_properties',
            'delete_properties',
            'upload_documents',
            'verify_documents',
            'view_sensitive_cnic',
            'view_transactions',
            'create_transactions',
            'update_transactions',
            'cancel_transactions',
            'void_transactions',
            'manage_payments',
            'view_reports',
        ] as $permission) {
            $this->assertContains($permission, $permissions, "agency owner membership is missing resolved permission [{$permission}]");
        }

        $this->assertFalse($user->isPlatformUser());
        $this->assertNotContains('admin.manage_agencies', $permissions);
        $this->assertNotContains('edit_cms', $permissions);
        $this->assertNotContains('manage_users', $permissions);
    }

    public function test_a_member_with_no_read_permission_cannot_dump_the_workspace(): void
    {
        [$user] = $this->agencyUser([], 'viewer');

        $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertForbidden();
    }
}
