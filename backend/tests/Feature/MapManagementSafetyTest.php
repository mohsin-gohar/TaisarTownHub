<?php

namespace Tests\Feature;

use App\Models\Map;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\EnterpriseTestCase;

/**
 * Regression coverage for the map-management hardening that the broader
 * management suite does not exercise.
 */
class MapManagementSafetyTest extends EnterpriseTestCase
{
    private function superAdmin(): array
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin', ['email' => 'maps-safe-' . bin2hex(random_bytes(4)) . '@example.test']);

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

    public function test_same_month_replacement_does_not_delete_the_new_file(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        // This is the exact path the previous implementation would reuse for
        // the replacement (same map id, same month, same extension), then
        // delete — destroying the freshly uploaded image.
        $oldPath = 'maps/' . now()->format('Y/m') . '/' . 'map_fixedid.jpg';
        Storage::disk('public')->put($oldPath, 'old-image-bytes');

        $map = $this->makeMap(['image_path' => $oldPath]);

        $this->post("/api/admin/maps/{$map->id}/replace-image", [
            'image' => UploadedFile::fake()->image('replacement.jpg', 1000, 700),
        ], $headers)->assertStatus(200)->assertJsonPath('success', true);

        $map->refresh();
        $this->assertNotSame($oldPath, $map->image_path);
        $this->assertTrue(
            Storage::disk('public')->exists($map->image_path),
            'the newly uploaded replacement must still exist after the old file is retired'
        );
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
    }

    public function test_stored_extension_follows_mime_not_client_filename(): void
    {
        Storage::fake('public');
        [, $headers] = $this->superAdmin();

        $source = UploadedFile::fake()->image('source.png', 120, 120);
        $tmp = tempnam(sys_get_temp_dir(), 'map') . '.jpg';
        copy($source->getPathname(), $tmp);
        // PNG bytes, misleading .jpg client name, no declared MIME.
        $file = new UploadedFile($tmp, 'payload.jpg', null, null, true);

        $this->post('/api/admin/maps', [
            'title' => 'Mismatched Extension Upload',
            'category' => 'sector_map',
            'image' => $file,
        ], $headers)->assertStatus(201);

        $map = Map::where('title', 'Mismatched Extension Upload')->firstOrFail();
        $this->assertStringEndsWith('.png', $map->image_path);
        $this->assertStringNotContainsString('.jpg', $map->image_path);
        $this->assertTrue(Storage::disk('public')->exists($map->image_path));
    }

    public function test_admin_can_clear_optional_map_fields(): void
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
}
