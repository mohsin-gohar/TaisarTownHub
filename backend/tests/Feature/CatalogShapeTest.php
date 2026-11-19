<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;

/**
 * G-12 regression: the public catalog must return REAL JSON lists.
 *
 * array_filter preserves keys, so skipping rows (draft/private/payload-deleted)
 * used to degrade e.g. "properties" into a JSON object keyed by leftover
 * indexes. The React boot only accepts arrays (Array.isArray) and silently
 * dropped the object — the whole public site then showed zero listings.
 */
class CatalogShapeTest extends EnterpriseTestCase
{
    public function test_catalog_properties_stay_a_list_when_rows_are_filtered(): void
    {
        // Public rows (legacy payloads predate the workflow: no explicit
        // approval/visibility keys default to approved + public).
        BusinessRecord::put('properties', 'pub-1', ['id' => 'pub-1', 'title' => 'Public One']);
        BusinessRecord::put('properties', 'pub-2', ['id' => 'pub-2', 'title' => 'Public Two']);

        // A row the catalog must skip (draft + private) — this is what used to
        // leave a key gap and turn the list into an object.
        BusinessRecord::put('properties', 'draft-1', [
            'id' => 'draft-1', 'title' => 'Draft', 'approval_status' => 'draft', 'visibility' => 'private',
        ]);

        // A payload-soft-deleted row (publicRows must drop it entirely).
        BusinessRecord::put('properties', 'gone-1', [
            'id' => 'gone-1', 'title' => 'Gone', 'deletedAt' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/catalog')->assertOk();

        $properties = $response->json('properties');
        $this->assertIsArray($properties);
        $this->assertSame([0, 1], array_keys($properties), 'properties must be a reindexed JSON list');

        $ids = array_map(fn ($p) => $p['id'], $properties);
        sort($ids);
        $this->assertSame(['pub-1', 'pub-2'], $ids, 'only approved+public rows are listed');
    }

    public function test_catalog_collections_exclude_payload_deleted_rows_and_stay_lists(): void
    {
        BusinessRecord::put('dealers', 'dl-1', ['id' => 'dl-1', 'fullName' => 'Dealer One']);
        BusinessRecord::put('dealers', 'gone-3', ['id' => 'gone-3', 'fullName' => 'Deleted Dealer', 'deletedAt' => '2026-01-02']);

        $response = $this->getJson('/api/catalog')->assertOk();

        $this->assertArrayNotHasKey('projects', $response->json());

        $dealers = $response->json('dealers');
        $this->assertSame([0], array_keys($dealers), 'dealers must stay a list');
        $this->assertSame('dl-1', $dealers[0]['id']);
    }

    public function test_catalog_does_not_publish_projects_or_private_media_payloads(): void
    {
        BusinessRecord::put('projects', 'legacy-project', [
            'id' => 'legacy-project',
            'title' => 'Legacy Project',
        ]);
        BusinessRecord::put('media', 'public-media', [
            'id' => 'public-media',
            'title' => 'Public Photo',
            'visibility' => 'public',
            'url' => 'https://example.com/photo.jpg',
            'buyerName' => 'Should Be Removed',
        ]);
        BusinessRecord::put('media', 'private-media', [
            'id' => 'private-media',
            'title' => 'Private Document',
            'visibility' => 'private',
            'buyerName' => 'Private Buyer',
        ]);

        $response = $this->getJson('/api/catalog')->assertOk();

        $this->assertArrayNotHasKey('projects', $response->json());
        $this->assertSame(['public-media'], array_column($response->json('media'), 'id'));
        $this->assertArrayNotHasKey('buyerName', $response->json('media.0'));
    }

    public function test_catalog_filters_legacy_rent_properties(): void
    {
        BusinessRecord::put('properties', 'legacy-sale', [
            'id' => 'legacy-sale',
            'title' => 'Legacy Sale',
            'listing_purpose' => 'sale',
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);
        BusinessRecord::put('properties', 'legacy-rent', [
            'id' => 'legacy-rent',
            'title' => 'Legacy Rent',
            'listing_purpose' => 'rent',
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);

        $response = $this->getJson('/api/catalog')->assertOk();
        $ids = array_column($response->json('properties'), 'id');
        $this->assertContains('legacy-sale', $ids);
        $this->assertNotContains('legacy-rent', $ids);
    }
}
