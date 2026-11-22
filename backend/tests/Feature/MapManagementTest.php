<?php

namespace Tests\Feature;

use App\Models\Map;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\EnterpriseTestCase;

/**
 * Map management: public visibility, admin authorization, CRUD, bulk upload,
 * safe image replacement, reordering, feature/toggle and file cleanup.
 */
class MapManagementTest extends EnterpriseTestCase
{
    private function superAdmin(): array
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin', ['email' => 'maps-' . bin2hex(random_bytes(4)) . '@example.test']);

        return [$admin, $this->authHeaders($this->tokenFor($admin))];
    }

    private function makeMap(array $attrs = []): Map
    {
        return Map::create(array_merge([
            'title' => 'Map ' . Str::random(6),
            'category' => 'sector_map',
            'image_path' => 'maps/test-' . Str::random(6) . '.jpg',
            'image_disk' => 'public',
            'image_mime' => 'image/jpeg',
            'image_size_bytes' => 10,
            'display_order' => 0,
            'is_active' => true,
            'is_featured' => false,
        ], $attrs));
    }

    public function test_public_index_returns_only_active_maps_in_order(): void
    {
        $later = $this->makeMap(['title' => 'Active Alpha', 'display_order' => 2, 'is_active' => true]);
        $first = $this->makeMap(['title' => 'Active Beta', 'display_order' => 1, 'is_active' => true]);
        $hidden = $this->makeMap(['title' => 'Hidden Map', 'display_order' => 0, 'is_active' => false]);

        $this->asGuest();
        $response = $this->getJson('/api/maps')->assertStatus(200)->assertJsonPath('success', true);

        $ids = collect($response->json('maps'))->pluck('id')->all();
        $this->assertSame([$first->id, $later->id], $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_public_index_filters_by_category_and_search(): void
    {
        $guide = $this->makeMap(['title' => 'Guide Masterplan', 'category' => 'guide_map', 'sector' => 'Sector 70']);
        $sector = $this->makeMap(['title' => 'Sector Layout', 'category' => 'sector_map', 'sector' => 'Sector 45']);

        $this->asGuest();

        $byCategory = $this->getJson('/api/maps?category=guide_map')->assertStatus(200)->json('maps');
        $this->assertSame([$guide->id], collect($byCategory)->pluck('id')->all());

        $bySearch = $this->getJson('/api/maps?search=Sector%2045')->assertStatus(200)->json('maps');
        $this->assertSame([$sector->id], collect($bySearch)->pluck('id')->all());
    }

    public function test_public_show_hides_inactive_maps(): void
    {
        $active = $this->makeMap(['title' => 'Visible Map', 'is_active' => true]);
        $inactive = $this->makeMap(['title' => 'Invisible Map', 'is_active' => false]);

        $this->asGuest();
        $this->getJson('/api/maps/' . $active->slug)->assertStatus(200)->assertJsonPath('map.id', $active->id);
        $this->getJson('/api/maps/' . $inactive->slug)->assertStatus(404);
    }

    public function test_guest_cannot_access_admin_map_endpoints(): void
    {
        $this->asGuest();
        $this->getJson('/api/admin/maps')->assertStatus(401);
        $this->postJson('/api/admin/maps', [])->assertStatus(401);
    }

    public function test_agency_member_cannot_manage_maps(): void
    {
        [$agent] = $this->agencyUser(['agency.view', 'properties.view']);
        $headers = $this->authHeaders($this->tokenFor($agent));

        $this->getJson('/api/admin/maps', $headers)->assertStatus(403);
        $this->postJson('/api/admin/maps', [], $headers)->assertStatus(403);
    }

    public function test_admin_can_create_map_with_image(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        $file = UploadedFile::fake()->image('sector-map.jpg', 1600, 900);

        $this->post('/api/admin/maps', [
            'title' => 'Sector 70-D Detailed Map',
            'sector' => 'Sector 70-D',
            'phase' => 'Phase 1',
            'category' => 'sector_map',
            'description' => 'Detailed sector layout.',
            'display_order' => 3,
            'is_active' => true,
            'is_featured' => true,
            'image' => $file,
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('map.title', 'Sector 70-D Detailed Map');

        $map = Map::where('title', 'Sector 70-D Detailed Map')->firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($map->image_path));
        $this->assertSame(1600, $map->image_width);
        $this->assertSame(900, $map->image_height);
        $this->assertNotEmpty($map->image_checksum);
        $this->assertDatabaseHas('audit_logs', ['action' => 'map.create', 'entity_id' => $map->id]);
    }

    public function test_stored_extension_is_derived_from_mime_not_client_filename(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        // A PNG whose client filename claims to be .jpg must be stored under
        // the extension derived from its real MIME type, not the client name.
        // (A .php name is rejected earlier by Laravel's `mimes` rule, which is
        // good; this asserts the controller's own derivation as well.)
        $source = UploadedFile::fake()->image('source.png', 120, 120);
        $tmp = tempnam(sys_get_temp_dir(), 'map') . '.jpg';
        copy($source->getPathname(), $tmp);
        // No declared MIME: the server sniffs the real bytes (PNG).
        $file = new UploadedFile($tmp, 'payload.jpg', null, null, true);

        $this->post('/api/admin/maps', [
            'title' => 'Mismatched Extension Upload',
            'category' => 'sector_map',
            'image' => $file,
        ], $headers)->assertStatus(201);

        $map = Map::where('title', 'Mismatched Extension Upload')->firstOrFail();
        $this->assertStringEndsWith('.png', $map->image_path, 'extension must follow the real MIME type');
        $this->assertStringNotContainsString('.jpg', $map->image_path);
        $this->assertTrue(Storage::disk('public')->exists($map->image_path));
    }

    public function test_bulk_upload_creates_one_map_per_image(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        $this->post('/api/admin/maps/bulk', [
            'category' => 'phase_map',
            'images' => [
                UploadedFile::fake()->image('north-phase.jpg', 1200, 800),
                UploadedFile::fake()->image('south-phase.jpg', 1200, 800),
            ],
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('failed_count', 0);

        $this->assertSame(2, Map::count());
        foreach (Map::all() as $map) {
            $this->assertTrue(Storage::disk('public')->exists($map->image_path));
            $this->assertSame('phase_map', $map->category);
        }
        $this->assertNotNull(Map::where('title', 'north-phase')->first());
        $this->assertNotNull(Map::where('title', 'south-phase')->first());
    }

    public function test_bulk_upload_rejects_missing_images(): void
    {
        [, $headers] = $this->superAdmin();

        $this->postJson('/api/admin/maps/bulk', ['category' => 'sector_map'], $headers)
            ->assertStatus(422);
        $this->assertSame(0, Map::count());
    }

    public function test_admin_can_update_details_and_clear_optional_fields(): void
    {
        [, $headers] = $this->superAdmin();
        $map = $this->makeMap([
            'title' => 'Old Title',
            'sector' => 'Sector A',
            'phase' => 'Phase 1',
            'description' => 'Old description',
        ]);

        $this->putJson("/api/admin/maps/{$map->id}", [
            'title' => 'New Title',
            'sector' => null,
            'phase' => null,
            'description' => null,
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('map.title', 'New Title');

        $map->refresh();
        $this->assertSame('New Title', $map->title);
        $this->assertNull($map->sector);
        $this->assertNull($map->phase);
        $this->assertNull($map->description);
    }

    public function test_replacing_an_image_keeps_the_new_file_and_removes_the_old(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        $oldPath = 'maps/' . now()->format('Y/m') . '/old-map.jpg';
        Storage::disk('public')->put($oldPath, 'old-image-bytes');
        $map = $this->makeMap(['image_path' => $oldPath, 'image_size_bytes' => 15]);

        // Same month + extension: the previous implementation could delete the
        // freshly stored replacement after overwriting the old path.
        $this->post("/api/admin/maps/{$map->id}/replace-image", [
            'image' => UploadedFile::fake()->image('replacement.jpg', 1000, 700),
        ], $headers)->assertStatus(200)->assertJsonPath('success', true);

        $map->refresh();
        $this->assertNotSame($oldPath, $map->image_path);
        $this->assertTrue(Storage::disk('public')->exists($map->image_path), 'new image must remain on disk');
        $this->assertFalse(Storage::disk('public')->exists($oldPath), 'old image must be removed after a verified upload');
        $this->assertSame(1000, $map->image_width);
    }

    public function test_toggle_feature_and_reorder(): void
    {
        [, $headers] = $this->superAdmin();
        $a = $this->makeMap(['title' => 'Alpha', 'is_active' => true, 'display_order' => 0]);
        $b = $this->makeMap(['title' => 'Bravo', 'is_active' => true, 'display_order' => 1, 'is_featured' => true]);

        $this->postJson("/api/admin/maps/{$a->id}/toggle", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('map.is_active', false);
        $this->assertFalse($a->fresh()->is_active);

        $this->postJson("/api/admin/maps/{$a->id}/feature", [], $headers)->assertStatus(200);
        $this->assertTrue($a->fresh()->is_featured);
        $this->assertFalse($b->fresh()->is_featured, 'only one map may be featured');

        $this->putJson('/api/admin/maps/reorder', ['order' => [$b->id, $a->id]], $headers)
            ->assertStatus(200);
        $this->assertSame(0, $b->fresh()->display_order);
        $this->assertSame(1, $a->fresh()->display_order);
    }

    public function test_delete_removes_record_and_image(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        $path = 'maps/delete-me.jpg';
        Storage::disk('public')->put($path, 'bytes');
        $map = $this->makeMap(['image_path' => $path]);

        $this->deleteJson("/api/admin/maps/{$map->id}", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNull(Map::find($map->id));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }
}
