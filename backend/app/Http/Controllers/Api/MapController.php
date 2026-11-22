<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Map;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MapController extends Controller
{
    use AuthorizesApi;

    /* ==================== Public ==================== */

    /** GET /api/maps — public active maps, ordered. */
    public function publicIndex(Request $request)
    {
        $query = Map::active()->ordered();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('sector')) {
            $query->where('sector', $request->sector);
        }
        if ($request->filled('phase')) {
            $query->where('phase', $request->phase);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('sector', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $maps = $query->get()->map(fn (Map $m) => $m->toPublicArray());

        return response()->json([
            'success' => true,
            'maps' => $maps,
            'count' => $maps->count(),
            'categories' => Map::CATEGORIES,
        ]);
    }

    /** GET /api/maps/{slug} — public single map. */
    public function publicShow(Request $request, string $slug)
    {
        $map = Map::active()->where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'map' => $map->toPublicArray(),
        ]);
    }

    /* ==================== Admin ==================== */

    /** GET /api/admin/maps — all maps including inactive (admin). */
    public function adminIndex(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $maps = Map::ordered()->with(['creator:id,name', 'updater:id,name'])->get();

        return response()->json([
            'success' => true,
            'maps' => $maps,
            'categories' => Map::CATEGORIES,
        ]);
    }

    /** POST /api/admin/maps — create a new map. */
    public function store(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $request->validate([
            'title' => 'required|string|max:255',
            'sector' => 'nullable|string|max:120',
            'phase' => 'nullable|string|max:60',
            'category' => 'required|string|in:' . implode(',', array_keys(Map::CATEGORIES)),
            'description' => 'nullable|string|max:5000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $file = $request->file('image');

        // Derive the stored extension from the verified MIME type, never from
        // the client-supplied filename (a PNG named `payload.php` must not be
        // written as an executable file on the public disk).
        $mime = $file->getMimeType();
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extByMime[$mime])) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.',
            ], 422);
        }
        $ext = $extByMime[$mime];

        $dims = @getimagesize($file->getRealPath());
        $width = $dims ? $dims[0] : null;
        $height = $dims ? $dims[1] : null;

        $mapId = 'map_' . bin2hex(random_bytes(8));
        $storedName = $mapId . '.' . $ext;
        $relativePath = 'maps/' . now()->format('Y/m') . '/' . $storedName;

        // Store on public disk
        $file->storeAs('maps/' . now()->format('Y/m'), $storedName, 'public');

        $map = Map::create([
            'id' => $mapId,
            'title' => strip_tags($request->title),
            'sector' => $request->sector ? strip_tags($request->sector) : null,
            'phase' => $request->phase,
            'category' => $request->category,
            'description' => $request->description ? strip_tags($request->description) : null,
            'image_path' => $relativePath,
            'image_disk' => 'public',
            'image_mime' => $mime,
            'image_size_bytes' => $file->getSize(),
            'image_width' => $width,
            'image_height' => $height,
            'image_checksum' => hash_file('sha256', $file->getRealPath()),
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'is_featured' => $request->boolean('is_featured', false),
            'created_by' => $request->user()->id,
        ]);

        $this->writeAudit($request, 'map.create', $map->id, "Map created: {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
            'metadata' => ['category' => $map->category, 'sector' => $map->sector],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map created successfully.',
            'map' => $map,
        ], 201);
    }

    /** PUT /api/admin/maps/{id} — update map details (no image). */
    public function update(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $map = Map::findOrFail($id);

        // Keep explicit nulls: they are how the admin clears an optional
        // field (sector/phase/description). Only keys actually present in the
        // request are forwarded, so an omitted field is left untouched.
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'sector' => 'nullable|string|max:120',
            'phase' => 'nullable|string|max:60',
            'category' => 'sometimes|string|in:' . implode(',', array_keys(Map::CATEGORIES)),
            'description' => 'nullable|string|max:5000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $payload = [];
        foreach (['title', 'sector', 'phase', 'category', 'description', 'display_order', 'is_active', 'is_featured'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        foreach (['title', 'sector', 'description'] as $textField) {
            if (array_key_exists($textField, $payload) && $payload[$textField] !== null) {
                $payload[$textField] = strip_tags($payload[$textField]);
            }
        }

        // If setting as featured, unset other featured maps
        if (!empty($payload['is_featured'])) {
            Map::where('id', '!=', $map->id)->update(['is_featured' => false]);
        }

        $map->fill($payload);
        $map->updated_by = $request->user()->id;
        $map->save();

        $this->writeAudit($request, 'map.update', $map->id, "Map updated: {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
            'metadata' => ['fields' => array_keys($payload)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map updated successfully.',
            'map' => $map,
        ]);
    }

    /** POST /api/admin/maps/{id}/replace-image — replace map image. */
    public function replaceImage(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $map = Map::findOrFail($id);

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $file = $request->file('image');
        $mime = $file->getMimeType();
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extByMime[$mime])) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.',
            ], 422);
        }
        $ext = $extByMime[$mime];

        $dims = @getimagesize($file->getRealPath());
        $width = $dims ? $dims[0] : null;
        $height = $dims ? $dims[1] : null;

        // Upload the replacement FIRST, verify it landed intact, then update
        // the record and only then remove the old file. A failed upload must
        // never leave the map with a deleted image.
        $oldDisk = $map->image_disk ?: 'public';
        $oldPath = $map->image_path;

        $dir = 'maps/' . now()->format('Y/m');
        // Unique name: replacing within the same month/extension must never
        // overwrite (and then delete) the freshly uploaded file.
        $storedName = $map->id . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relativePath = $dir . '/' . $storedName;

        $file->storeAs($dir, $storedName, 'public');

        $disk = Storage::disk('public');
        if (!$disk->exists($relativePath) || (int) $disk->size($relativePath) !== (int) $file->getSize()) {
            // Best-effort cleanup of the partial upload; the old image stays.
            $disk->delete($relativePath);

            return response()->json([
                'success' => false,
                'message' => 'The new image could not be stored. The existing image was kept.',
            ], 500);
        }

        $map->update([
            'image_path' => $relativePath,
            'image_disk' => 'public',
            'image_mime' => $mime,
            'image_size_bytes' => $file->getSize(),
            'image_width' => $width,
            'image_height' => $height,
            'image_checksum' => hash_file('sha256', $file->getRealPath()),
            'updated_by' => $request->user()->id,
        ]);

        // Only now that the new image is persisted and referenced can the old
        // file be removed. Never delete a path that the row still points to.
        if ($oldPath && $oldPath !== $relativePath && $oldPath !== $map->image_path) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        $this->writeAudit($request, 'map.replace_image', $map->id, "Map image replaced: {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map image replaced successfully.',
            'map' => $map,
        ]);
    }

    /** POST /api/admin/maps/bulk — upload several map images at once. */
    public function bulkStore(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $request->validate([
            'images' => 'required|array|min:1|max:50',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'category' => 'nullable|string|in:' . implode(',', array_keys(Map::CATEGORIES)),
            'sector' => 'nullable|string|max:120',
            'phase' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
        ]);

        $category = $request->input('category') ?: 'sector_map';
        $sector = $request->filled('sector') ? strip_tags((string) $request->input('sector')) : null;
        $phase = $request->filled('phase') ? (string) $request->input('phase') : null;
        $isActive = $request->boolean('is_active', true);

        $disk = Storage::disk('public');
        $nextOrder = ((int) Map::max('display_order')) + 1;
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        $created = [];
        $failed = [];

        foreach ($request->file('images', []) as $file) {
            $originalName = $file->getClientOriginalName();
            try {
                $mime = $file->getMimeType();
                if (!isset($extByMime[$mime])) {
                    throw new \RuntimeException('Unsupported image type.');
                }
                $ext = $extByMime[$mime];

                $dims = @getimagesize($file->getRealPath());
                $title = trim((string) pathinfo($originalName, PATHINFO_FILENAME));
                $title = $title !== '' ? strip_tags($title) : 'Map ' . $nextOrder;

                $mapId = 'map_' . bin2hex(random_bytes(8));
                $dir = 'maps/' . now()->format('Y/m');
                $storedName = $mapId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $relativePath = $dir . '/' . $storedName;

                $file->storeAs($dir, $storedName, 'public');
                if (!$disk->exists($relativePath) || (int) $disk->size($relativePath) !== (int) $file->getSize()) {
                    $disk->delete($relativePath);
                    throw new \RuntimeException('Stored file failed verification.');
                }

                $map = Map::create([
                    'id' => $mapId,
                    'title' => $title,
                    'sector' => $sector,
                    'phase' => $phase,
                    'category' => $category,
                    'description' => null,
                    'image_path' => $relativePath,
                    'image_disk' => 'public',
                    'image_mime' => $mime,
                    'image_size_bytes' => $file->getSize(),
                    'image_width' => $dims ? $dims[0] : null,
                    'image_height' => $dims ? $dims[1] : null,
                    'image_checksum' => hash_file('sha256', $file->getRealPath()),
                    'display_order' => $nextOrder++,
                    'is_active' => $isActive,
                    'is_featured' => false,
                    'created_by' => $request->user()->id,
                ]);

                $created[] = $map;
            } catch (\Throwable $e) {
                $failed[] = [
                    'file' => $originalName,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($created !== []) {
            $this->writeAudit($request, 'map.bulk_create', 'maps', 'Bulk map upload: ' . count($created) . ' created', [
                'entity_type' => 'map',
                'cross_agency' => true,
                'metadata' => ['created' => count($created), 'failed' => count($failed)],
            ]);
        }

        return response()->json([
            'success' => $created !== [],
            'message' => $created === []
                ? 'No maps could be uploaded.'
                : count($created) . ' map(s) uploaded' . ($failed !== [] ? ', ' . count($failed) . ' failed.' : '.'),
            'maps' => $created,
            'failed' => $failed,
            'created_count' => count($created),
            'failed_count' => count($failed),
        ], $created === [] ? 422 : 201);
    }

    /** DELETE /api/admin/maps/{id} — delete map and its image. */
    public function destroy(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $map = Map::findOrFail($id);

        // Delete image file
        if ($map->image_path && Storage::disk($map->image_disk ?: 'public')->exists($map->image_path)) {
            Storage::disk($map->image_disk ?: 'public')->delete($map->image_path);
        }

        $this->writeAudit($request, 'map.delete', $map->id, "Map deleted: {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
        ]);

        $map->delete();

        return response()->json([
            'success' => true,
            'message' => 'Map deleted successfully.',
        ]);
    }

    /** POST /api/admin/maps/reorder — reorder maps. */
    public function reorder(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'string|max:64',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->order as $index => $id) {
                Map::where('id', $id)->update(['display_order' => $index]);
            }
        });

        $this->writeAudit($request, 'map.reorder', 'maps', 'Maps reordered', [
            'entity_type' => 'map',
            'cross_agency' => true,
            'metadata' => ['count' => count($request->order)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map order updated.',
        ]);
    }

    /** POST /api/admin/maps/{id}/toggle — enable/disable map. */
    public function toggle(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $map = Map::findOrFail($id);
        $map->is_active = !$map->is_active;
        $map->updated_by = $request->user()->id;
        $map->save();

        $this->writeAudit($request, 'map.toggle', $map->id, "Map " . ($map->is_active ? 'enabled' : 'disabled') . ": {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
            'metadata' => ['is_active' => $map->is_active],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map ' . ($map->is_active ? 'enabled' : 'disabled') . '.',
            'map' => $map,
        ]);
    }

    /** POST /api/admin/maps/{id}/feature — set as featured map. */
    public function feature(Request $request, string $id)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $map = Map::findOrFail($id);

        // Unset other featured maps first
        Map::where('id', '!=', $map->id)->update(['is_featured' => false]);

        $map->is_featured = true;
        $map->updated_by = $request->user()->id;
        $map->save();

        $this->writeAudit($request, 'map.feature', $map->id, "Map featured: {$map->title}", [
            'entity_type' => 'map',
            'entity_id' => $map->id,
            'cross_agency' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map set as featured.',
            'map' => $map,
        ]);
    }
}
