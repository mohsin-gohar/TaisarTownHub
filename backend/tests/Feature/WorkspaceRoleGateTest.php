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

    public function test_a_member_with_no_read_permission_cannot_dump_the_workspace(): void
    {
        [$user] = $this->agencyUser([], 'viewer');

        $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertForbidden();
    }

    public function test_agency_owner_cannot_overwrite_platform_cms_settings(): void
    {
        [$user] = $this->agencyUser(Permissions::agencyRoleGrants('agency_owner'), 'agency_owner');
        $before = BusinessRecord::where('collection', 'cms')
            ->where('record_id', 'default')
            ->value('payload');

        $response = $this->putJson('/api/workspace', [
            'collections' => [
                'cms' => ['heroHeadline' => 'Tenant must not replace the platform homepage'],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertContains('cms', $response->json('denied'));
        $this->assertSame(
            $before,
            BusinessRecord::where('collection', 'cms')->where('record_id', 'default')->value('payload'),
            'agency-scoped users must not overwrite the platform-wide CMS record'
        );
    }

}
