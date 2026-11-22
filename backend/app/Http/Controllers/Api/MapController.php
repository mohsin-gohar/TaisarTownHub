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
        $ext = strtolower($file->getClientOriginalExtension());
        $dims = @getimagesize($file->getRealPath());
        $width = $dims ? $dims[0] : null;
        $height = $dims ? $dims[1] : null;

        $mapId = 'map_' . bin2hex(random_bytes(8));
        $storedName = $mapId . '.' . $ext;
        $relativePath = 'maps/' . now()->format('Y/m') . '/' . $storedName;

        // Validate MIME type properly
        $mime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.',
            ], 422);
        }

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

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'sector' => 'nullable|string|max:120',
            'phase' => 'nullable|string|max:60',
            'category' => 'sometimes|string|in:' . implode(',', array_keys(Map::CATEGORIES)),
            'description' => 'nullable|string|max:5000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $payload = $request->only(['title', 'sector', 'phase', 'category', 'description', 'display_order', 'is_active', 'is_featured']);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        if (isset($payload['title'])) {
            $payload['title'] = strip_tags($payload['title']);
        }
        if (isset($payload['sector'])) {
            $payload['sector'] = strip_tags($payload['sector']);
        }
        if (isset($payload['description'])) {
            $payload['description'] = strip_tags($payload['description']);
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
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported image type. Allowed: JPEG, PNG, WebP.',
            ], 422);
        }

        $dims = @getimagesize($file->getRealPath());
        $width = $dims ? $dims[0] : null;
        $height = $dims ? $dims[1] : null;

        // Delete old image file
        if ($map->image_path && Storage::disk($map->image_disk ?: 'public')->exists($map->image_path)) {
            Storage::disk($map->image_disk ?: 'public')->delete($map->image_path);
        }

        $storedName = $map->id . '.' . $ext;
        $relativePath = 'maps/' . now()->format('Y/m') . '/' . $storedName;

        $file->storeAs('maps/' . now()->format('Y/m'), $storedName, 'public');

        $map->update([
            'image_path' => $relativePath,
            'image_mime' => $mime,
            'image_size_bytes' => $file->getSize(),
            'image_width' => $width,
            'image_height' => $height,
            'image_checksum' => hash_file('sha256', $file->getRealPath()),
            'updated_by' => $request->user()->id,
        ]);

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
