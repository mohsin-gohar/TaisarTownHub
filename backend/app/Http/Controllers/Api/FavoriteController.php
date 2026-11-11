<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyFavorite;
use Illuminate\Http\Request;

/** Per-account favorites (replaces the per-browser localStorage version). */
class FavoriteController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        $user = $request->user();

        $page = PropertyFavorite::where('user_id', $user->id)
            ->whereIn('property_id', Property::query()->publiclyVisible()->select('id'))
            ->with('property')
            ->latest()
            ->paginate(min((int) $request->get('per_page', 24), 100));

        $ids = $page->pluck('property_id');

        return response()->json([
            'success' => true,
            'favorites' => Property::whereIn('id', $ids)->get(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** POST /api/favorites/{propertyId} — toggle. */
    public function toggle(Request $request, string $propertyId)
    {
        $user = $request->user();

        $property = Property::publiclyVisible()->where('id', $propertyId)->first();
        if (!$property) {
            abort(404, 'Property not found.');
        }

        $existing = PropertyFavorite::where('user_id', $user->id)
            ->where('property_id', $propertyId)
            ->first();

        if ($existing) {
            $existing->delete();
            $favorited = false;
        } else {
            PropertyFavorite::create([
                'user_id' => $user->id,
                'property_id' => $propertyId,
            ]);
            $favorited = true;
        }

        return response()->json([
            'success' => true,
            'favorited' => $favorited,
            'count' => PropertyFavorite::where('property_id', $propertyId)->count(),
        ]);
    }

    public function destroy(Request $request, string $propertyId)
    {
        PropertyFavorite::where('user_id', $request->user()->id)
            ->where('property_id', $propertyId)
            ->delete();

        return response()->json(['success' => true, 'favorited' => false]);
    }
}
