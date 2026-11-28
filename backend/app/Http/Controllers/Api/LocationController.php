<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\PropertyCategory;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Location & category structures (Taisar Town sectors/blocks + property
 * categories). Reads are public; writes require properties.approve (platform).
 */
class LocationController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        // Count public listings with correlated subqueries instead of
        // issuing one COUNT query for every sector while mapping the result.
        $sectors = Sector::where('is_active', true)
            ->withCount(['properties as public_listing_count' => fn ($q) => $q->publiclyVisible()])
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Sector $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'phase' => $s->phase,
                'description' => $s->description,
                'blocks' => $s->blocks->map(fn (Block $b) => [
                    'id' => $b->id, 'name' => $b->name, 'slug' => $b->slug,
                ])->values(),
                'listing_count' => (int) $s->public_listing_count,
            ]);

        // Same aggregate strategy for categories; this keeps query count
        // constant as the number of categories grows.
        $categories = PropertyCategory::where('is_active', true)
            ->withCount(['properties as public_listing_count' => fn ($q) => $q->publiclyVisible()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PropertyCategory $c) => [
                'id' => $c->id,
                'key' => $c->key,
                'name' => $c->name,
                'group' => $c->group,
                'icon' => $c->icon,
                'listing_count' => (int) $c->public_listing_count,
            ]);

        return response()->json([
            'success' => true,
            'sectors' => $sectors,
            'categories' => $categories,
        ]);
    }

    public function storeSector(Request $request)
    {
        $this->requirePermission($request, 'properties.approve');

        $request->validate([
            'name' => 'required|string|max:100',
            'phase' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $slug = Str::slug($request->name) ?: 'sector-' . bin2hex(random_bytes(3));
        if (Sector::where('slug', $slug)->exists()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $sector = Sector::create([
            'id' => 'sec_' . bin2hex(random_bytes(8)),
            'name' => $request->name,
            'slug' => $slug,
            'phase' => $request->phase,
            'description' => $request->description,
            'sort_order' => $request->get('sort_order', 0),
        ]);

        $this->writeAudit($request, 'sector.create', $sector->id, "Sector created: {$sector->name}", [
            'entity_type' => 'sector',
            'entity_id' => $sector->id,
        ]);

        return response()->json(['success' => true, 'sector' => $sector], 201);
    }

    public function storeBlock(Request $request)
    {
        $this->requirePermission($request, 'properties.approve');

        $request->validate([
            'sector_id' => 'required|string|exists:sectors,id',
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $slug = Str::slug($request->name) ?: 'block-' . bin2hex(random_bytes(3));

        $block = Block::create([
            'id' => 'blk_' . bin2hex(random_bytes(8)),
            'sector_id' => $request->sector_id,
            'name' => $request->name,
            'slug' => $slug,
            'sort_order' => $request->get('sort_order', 0),
        ]);

        $this->writeAudit($request, 'block.create', $block->id, "Block created: {$block->name}", [
            'entity_type' => 'block',
            'entity_id' => $block->id,
        ]);

        return response()->json(['success' => true, 'block' => $block], 201);
    }

    public function storeCategory(Request $request)
    {
        $this->requirePermission($request, 'properties.approve');

        $request->validate([
            'key' => 'required|string|max:60|unique:property_categories,key',
            'name' => 'required|string|max:100',
            'group' => 'required|in:residential,commercial,rent',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $category = PropertyCategory::create($request->only(['key', 'name', 'group', 'icon', 'sort_order']));

        $this->writeAudit($request, 'category.create', $category->key, "Category created: {$category->name}", [
            'entity_type' => 'property_category',
            'entity_id' => (string) $category->id,
        ]);

        return response()->json(['success' => true, 'category' => $category], 201);
    }
}
