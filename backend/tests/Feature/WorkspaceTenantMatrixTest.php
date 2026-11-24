<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tenant-isolation matrix for every workspace collection.
 *
 * The 13 agency-scoped collections must never expose or mutate another
 * tenant's rows, and replace mode must only delete the writer's own rows.
 * Projects/testimonials are platform-global public content and are covered by
 * a separate role test.
 */
class WorkspaceTenantMatrixTest extends EnterpriseTestCase
{
    /**
     * collection => [read permission, write permission, replace permission]
     */
    public static function scopedCollections(): array
    {
        return [
            'properties' => ['properties', ['properties.view'], ['properties.create'], ['properties.delete']],
            'dealers' => ['dealers', ['properties.view'], ['manage_dealers'], ['manage_dealers']],
            'sellers' => ['sellers', ['manage_sellers'], ['manage_sellers'], ['manage_sellers']],
            'buyers' => ['buyers', ['manage_buyers'], ['manage_buyers'], ['manage_buyers']],
            'transactions' => ['transactions', ['finance.view'], ['finance.create_transaction'], ['void_transactions']],
            'payments' => ['payments', ['finance.view'], ['finance.manage_payments'], ['finance.manage_payments']],
            'commissions' => ['commissions', ['finance.view'], ['finance.manage_payments'], ['pay_commissions']],
            'files' => ['files', ['documents.view'], ['documents.upload'], ['documents.delete']],
            'plots' => ['plots', ['properties.view'], ['properties.update'], ['properties.delete']],
            'media' => ['media', ['properties.view'], ['manage_media'], ['manage_media']],
            'videos' => ['videos', ['properties.view'], ['manage_media'], ['manage_media']],
            'inquiries' => ['inquiries', ['crm.view'], ['crm.update_lead'], ['crm.delete_lead']],
            'subscribers' => ['subscribers', ['crm.view'], ['crm.update_lead'], ['crm.delete_lead']],
        ];
    }

    private function seedRecord(string $collection, string $id, string $title, ?string $agencyId): void
    {
        BusinessRecord::put($collection, $id, ['id' => $id, 'title' => $title]);
        if ($agencyId !== null) {
            BusinessRecord::where('collection', $collection)->where('record_id', $id)
                ->update(['agency_id' => $agencyId]);
        }
    }

    private function record(string $collection, string $id): BusinessRecord
    {
        return BusinessRecord::where('collection', $collection)
            ->where('record_id', $id)
            ->firstOrFail();
    }

    #[DataProvider('scopedCollections')]
    public function test_scoped_collection_reads_and_writes_are_tenant_isolated(
        string $collection,
        array $readPermissions,
        array $writePermissions,
        array $replacePermissions
    ): void {
        [$user, $agency] = $this->agencyUser(array_values(array_unique(array_merge(
            $readPermissions,
            $writePermissions,
            $replacePermissions
        ))));
        $other = $this->makeAgency('Rival Agency');

        $ownId = 'own-'.$collection;
        $foreignId = 'foreign-'.$collection;
        $legacyId = 'legacy-'.$collection;

        $this->seedRecord($collection, $ownId, 'Own row', $agency->id);
        $this->seedRecord($collection, $foreignId, 'Foreign row', $other->id);
        $this->seedRecord($collection, $legacyId, 'Legacy row', null);

        // ---- read isolation ----
        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json($collection);

        $this->assertIsArray($body, "{$collection} must be a list");
        $ids = array_column($body, 'id');
        $this->assertContains($ownId, $ids, "{$collection}: own row must be visible");
        $this->assertNotContains($foreignId, $ids, "{$collection}: foreign row must not be visible");
        $this->assertNotContains($legacyId, $ids, "{$collection}: platform-owned legacy row must not be visible");

        // ---- write isolation: own row updates, foreign row is untouched ----
        $this->putJson('/api/workspace', [
            'collections' => [$collection => [
                ['id' => $ownId, 'title' => 'Own updated'],
                ['id' => $foreignId, 'title' => 'Hijacked'],
            ]],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame('Own updated', $this->record($collection, $ownId)->payload['title']);
        $this->assertSame('Foreign row', $this->record($collection, $foreignId)->payload['title']);
        $this->assertSame('Legacy row', $this->record($collection, $legacyId)->payload['title']);
        $this->assertSame($other->id, $this->record($collection, $foreignId)->agency_id);
        $this->assertNull($this->record($collection, $legacyId)->agency_id);

        // ---- replace is scoped to the writer's agency ----
        $this->putJson('/api/workspace', [
            'collections' => [$collection => [
                ['id' => 'fresh-'.$collection, 'title' => 'Fresh'],
            ]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertNotNull(
            $this->record($collection, $ownId)->deleted_at,
            "{$collection}: own row absent from replace payload must be removed"
        );
        $this->assertNull(
            $this->record($collection, $foreignId)->deleted_at,
            "{$collection}: foreign row must survive a member replace"
        );
        $this->assertNull(
            $this->record($collection, $legacyId)->deleted_at,
            "{$collection}: legacy platform row must survive a member replace"
        );
    }

    public function test_global_project_and_testimonial_content_is_read_only_for_standard_agency_roles(): void
    {
        [$user, $agency] = $this->agencyUser([
            'properties.view', 'view_properties', 'crm.view',
        ]);

        $this->seedRecord('projects', 'global-project', 'Platform project', $agency->id);
        $this->seedRecord('testimonials', 'global-testimonial', 'Public testimonial', $agency->id);

        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json();

        $this->assertContains('global-project', array_column($body['projects'], 'id'));
        $this->assertContains('global-testimonial', array_column($body['testimonials'], 'id'));

        $response = $this->putJson('/api/workspace', [
            'collections' => [
                'projects' => [['id' => 'global-project', 'title' => 'Hijacked project']],
                'testimonials' => [['id' => 'global-testimonial', 'title' => 'Hijacked testimonial']],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertContains('projects', $response->json('denied'));
        $this->assertContains('testimonials', $response->json('denied'));
        $this->assertSame('Platform project', $this->record('projects', 'global-project')->payload['title']);
        $this->assertSame('Public testimonial', $this->record('testimonials', 'global-testimonial')->payload['title']);
    }
}
