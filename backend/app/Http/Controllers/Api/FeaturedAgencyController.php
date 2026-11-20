<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\AgencyPromotionService;
use Illuminate\Http\Request;

/**
 * Public, read-only homepage roster.
 *
 * The endpoint is intentionally thin: eligibility, ordering and badges come
 * from AgencyPromotionService so the public page and the admin desk always
 * agree about who is actually featured.
 */
class FeaturedAgencyController extends Controller
{
    public function __construct(private readonly AgencyPromotionService $promotions)
    {
    }

    /** GET /api/agencies/featured */
    public function index(Request $request)
    {
        $limit = $request->filled('limit') ? (int) $request->limit : null;
        $featured = $this->promotions->publicFeatured($limit);

        // Verified-agency fallback keeps the homepage useful while the first
        // paid placements are being configured. Fallback cards are never
        // labelled Featured/Sponsored.
        $fallback = [];
        if ($featured === []) {
            $fallback = Agency::query()
                ->where('status', 'active')
                ->where('verification_status', 'verified')
                ->withCount(['properties as public_listings_count' => fn ($query) => $query->publiclyVisible()])
                ->orderByDesc('public_listings_count')
                ->orderBy('name')
                ->limit(max(1, min(6, $limit ?? 3)))
                ->get()
                ->map(fn (Agency $agency) => [
                    'id' => $agency->id,
                    'name' => $agency->name,
                    'slug' => $agency->slug,
                    'logo_url' => $agency->logo_url,
                    'cover_url' => $agency->cover_url,
                    'description' => $agency->description,
                    'city' => $agency->city,
                    'office_address' => $agency->office_address,
                    'phone' => $agency->phone,
                    'whatsapp' => $agency->whatsapp,
                    'verification_status' => $agency->verification_status,
                    'sale_listing_count' => (int) ($agency->public_listings_count ?? 0),
                    'position' => null,
                    'badge' => null,
                    'is_paid' => false,
                    'featured_since' => null,
                    'featured_until' => null,
                ])
                ->all();
        }

        return response()->json([
            'success' => true,
            'agencies' => $featured !== [] ? $featured : $fallback,
            'featured' => $featured !== [],
            'count' => count($featured !== [] ? $featured : $fallback),
            'max_slots' => $this->promotions->maxSlots(),
        ]);
    }
}
