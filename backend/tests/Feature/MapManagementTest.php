<?php

namespace Tests\Feature;

use App\Models\Map;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Map Management API coverage.
 *
 * Covers the public catalogue contract and the Super Admin management surface:
 *  - public index only exposes active maps in the documented plain-array shape;
 *  - public show resolves by slug and hides inactive / unknown maps;
 *  - public filters (category / sector / phase / search) stay server-side;
 *  - admin endpoints require a real Super Admin permission;
 *  - CRUD + image upload + safe replacement + reorder + toggle + feature + delete;
 *  - bulk upload creates one map per uploaded image.
 */
class MapManagementTest extends EnterpriseTestCase
{
    private function superAdmin(): User
    {
        $this->seedPlatformRole('super_admin');

        return $this->makeUser('super_admin', ['email' => 'maps-admin@example.test']);
    }

    private function authHeadersFor(User $user): array
    {
        return $this->authHeaders($this->tokenFor($user));
    }

    private function makeMap(array $attrs = []): Map
    {
        return Map::create(array_merge([
            'id' => 'map_' . substr(md5(uniqid('', true)), 0, 12),
            'title' => 'Taiser Town Map',
            'slug' => 'taiser-town-map-' . substr(md5(uniqid('', true)), 0, 6),
            'category' => 'sector_map',
            'description' => 'Test map description',
            'image_path' => 'maps/taiser-town-map.jpg',
            'image_disk' => 'public',
            'image_mime' => 'image/jpeg',
            'image_size_bytes' => 1024,
            'image_width' => 100,
            'image_height' => 100,
            'display_order' => 0,
            'is_active' => true,
            'is_featured' => false,
        ], $attrs));
    }

    public function test_public_maps_endpoint_returns_only_active_maps_in_public_shape(): void
    {
        $active = $this->makeMap(['title' => 'Active Map', 'slug' => 'public-active-map', 'is_active' => true]);
        $this->makeMap(['title' => 'Hidden Map', 'slug' => 'public-hidden-map', 'is_active' => false]);

        $response = $this->getJson('/api/maps')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, count($response->json('maps')), 'public index must only return active maps');
        $this->assertSame($active->id, $response->json('maps.0.id'));
        $this->assertSame(1, $response->json('count'));
        $this->assertArrayHasKey('categories', $response->json());
    }

    public function test_public_map_show_returns_the_active_map_and_rejects_unknown_or_inactive(): void
    {
        $map = $this->makeMap(['slug' => 'taiser-town-guide', 'is_active' => true]);

        $this->getJson('/api/maps/taiser-town-guide')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('map.slug', 'taiser-town-guide');

        $this->getJson('/api/maps/does-not-exist')->assertNotFound();

        $this->makeMap(['slug' => 'taiser-town-hidden', 'is_active' => false]);
        $this->getJson('/api/maps/taiser-town-hidden')->assertNotFound();
    }

    public function test_public_maps_support_category_sector_and_search_filters(): void
    {
        $sectorMap = $this->makeMap([
            'title' => 'Sector 70-D Map',
            'slug' => 'map-sector-70d',
            'category' => 'sector_map',
            'sector' => 'Sector 70-D',
            'is_active' => true,
        ]);
        $phaseMap = $this->makeMap([
            'title' => 'Phase Map',
            'slug' => 'map-phase-1',
            'category' => 'phase_map',
            'phase' => 'Phase 1',
            'is_active' => true,
        ]);
        $this->makeMap(['title' => 'Taiser Town Guide', 'slug' => 'map-guide', 'category' => 'guide_map', 'is_active' => true]);

        $this->getJson('/api/maps?category=phase_map')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('maps.0.slug', 'map-phase-1');

        $this->getJson('/api/maps?sector=Sector%2070-D')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('maps.0.slug', 'map-sector-70d');

        $this->getJson('/api/maps?search=70-D')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('maps.0.slug', 'map-sector-70d');
    }

    public function test_admin_maps_require_super_admin_authorization(): void
    {
        // Guest is rejected before any admin logic runs.
        $this->getJson('/api/admin/maps')->assertUnauthorized();

        // Agency members keep agency-scoped rights only and never get the
        // platform-wide map management permission through agency grants.
        [$member] = $this->agencyUser(['agency.view', 'view_properties']);
        $this->getJson('/api/admin/maps', $this->authHeadersFor($member))->assertForbidden();

        // Platform staff without the platform key are also rejected.
        $operator = $this->makeUser('operator', ['email' => 'maps-operator@example.test']);
        $this->getJson('/api/admin/maps', $this->authHeadersFor($operator))->assertForbidden();
    }

    public function test_super_admin_can_create_map_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $response = $this->post('/api/admin/maps', [
            'title' => 'Sector 70-D Map',
            'sector' => 'Sector 70-D',
            'phase' => 'Phase 1',
            'category' => 'sector_map',
            'description' => 'Sector map description.',
            'display_order' => 3,
            'is_active' => true,
            'is_featured' => false,
            'image' => UploadedFile::fake()->image('sector-70d.jpg', 800, 600),
        ], $this->authHeadersFor($admin))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $map = Map::where('title', 'Sector 70-D Map')->firstOrFail();
        $this->assertSame('sector_map', $map->category);
        $this->assertSame(3, $map->display_order);
        Storage::disk('public')->assertExists($map->image_path);
    }

    public function test_map_create_rejects_invalid_category_and_missing_image(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $this->postJson('/api/admin/maps', [
            'title' => 'Bogus Category Map',
            'category' => 'not_a_real_category',
            'image' => UploadedFile::fake()->image('bogus.jpg'),
        ], $this->authHeadersFor($admin))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/admin/maps', [
            'title' => 'No Image Map',
            'category' => 'sector_map',
        ], $this->authHeadersFor($admin))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_replace_map_image_and_retire_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        Storage::disk('public')->put('maps/taiser-town-map.jpg', 'legacy-bytes');
        $map = $this->makeMap(['slug' => 'taiser-town-map', 'image_path' => 'maps/taiser-town-map.jpg']);
        Storage::disk('public')->assertExists('maps/taiser-town-map.jpg');

        $this->post('/api/admin/maps/' . $map->id . '/replace-image', [
            'image' => UploadedFile::fake()->image('taiser-town-map-v2.jpg', 1200, 900),
        ], $this->authHeadersFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Storage::disk('public')->assertMissing('maps/taiser-town-map.jpg');
    }

    public function test_failed_map_image_replacement_keeps_existing_image(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        Storage::disk('public')->put('maps/taiser-town-map.jpg', 'legacy-bytes');
        $map = $this->makeMap(['slug' => 'taiser-town-map', 'image_path' => 'maps/taiser-town-map.jpg']);

        // A rejected upload (wrong MIME) must never touch the stored map image.
        $this->post('/api/admin/maps/' . $map->id . '/replace-image', [
            'image' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ], $this->authHeadersFor($admin))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Storage::disk('public')->assertExists('maps/taiser-town-map.jpg');
        $this->assertSame('maps/taiser-town-map.jpg', $map->fresh()->image_path);
    }

    public function test_admin_can_reorder_toggle_feature_and_delete_maps(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $first = $this->makeMap(['title' => 'First Map', 'slug' => 'first-map', 'display_order' => 0]);
        $second = $this->makeMap(['title' => 'Second Map', 'slug' => 'second-map', 'display_order' => 1]);
        $third = $this->makeMap(['title' => 'Third Map', 'slug' => 'third-map', 'display_order' => 2]);

        $this->putJson('/api/admin/maps/reorder', ['order' => [$third->id, $first->id, $second->id]], $this->authHeadersFor($admin))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $third->fresh()->display_order);
        $this->assertSame(1, $first->fresh()->display_order);
        $this->assertSame(2, $second->fresh()->display_order);

        $this->postJson('/api/admin/maps/' . $second->id . '/toggle', [], $this->authHeadersFor($admin))
            ->assertOk()
            ->assertJsonPath('map.is_active', false);

        $this->postJson('/api/admin/maps/' . $second->id . '/toggle', [], $this->authHeadersFor($admin))
            ->assertOk()
            ->assertJsonPath('map.is_active', true);

        $this->postJson('/api/admin/maps/' . $first->id . '/feature', [], $this->authHeadersFor($admin))
            ->assertOk()
            ->assertJsonPath('map.is_featured', true);

        $this->assertTrue((bool) $first->fresh()->is_featured);

        // Featuring another map must clear the previous featured flag.
        $this->postJson('/api/admin/maps/' . $third->id . '/feature', [], $this->authHeadersFor($admin))
            ->assertOk();

        $this->assertTrue((bool) $third->fresh()->is_featured);
        $this->assertFalse((bool) $first->fresh()->is_featured);
        $this->assertFalse((bool) $second->fresh()->is_featured);

        $this->deleteJson('/api/admin/maps/' . $third->id, [], $this->authHeadersFor($admin))
            ->assertOk();

        $this->assertSoftDeleted('maps', ['id' => $third->id]);
    }

    public function test_super_admin_can_bulk_upload_maps(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $response = $this->post('/api/admin/maps/bulk', [
            'images' => [
                UploadedFile::fake()->image('map-one.jpg', 800, 600),
                UploadedFile::fake()->image('map-two.jpg', 900, 700),
                UploadedFile::fake()->image('map-three.jpg', 1000, 800),
            ],
            'titles' => ['Sector Map One', 'Sector Map Two', 'Guide Map'],
            'categories' => ['sector_map', 'phase_map', 'guide_map'],
            'sectors' => ['Sector 70-D', 'Sector 71-D', null],
            'featured' => [true, false, false],
        ], $this->authHeadersFor($admin))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 3);

        $this->assertDatabaseHas('maps', ['title' => 'Sector Map One', 'category' => 'sector_map']);
        $this->assertDatabaseHas('maps', ['title' => 'Sector Map Two', 'category' => 'phase_map']);
        $this->assertDatabaseHas('maps', ['title' => 'Guide Map', 'category' => 'guide_map']);
        $this->assertSame(3, Map::count());
    }

    public function test_bulk_upload_rejects_empty_or_invalid_payloads(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();

        $this->post('/api/admin/maps/bulk', [], $this->authHeadersFor($admin))
            ->assertStatus(422);

        $this->post('/api/admin/maps/bulk', [
            'images' => [UploadedFile::fake()->image('map-one.jpg')],
            'categories' => ['not_a_real_category'],
        ], $this->authHeadersFor($admin))
            ->assertStatus(422);
    }
}
