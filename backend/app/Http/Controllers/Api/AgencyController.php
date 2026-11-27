<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\VerifyEmailLink;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencyController extends Controller
{
    use AuthorizesApi;

    /**
     * POST /api/agencies/register — public agency self-registration.
     * Creates: user (owner) + agency (pending) + agency_owner membership.
     * Gated by the `registration.agency_enabled` system setting.
     */
    public function register(Request $request)
    {
        if (!SystemSetting::get('registration.agency_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Agency registration is currently disabled.',
            ], 403);
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->email))]);

        $request->validate([
            'name' => 'required|string|min:3|max:120',
            'contactName' => 'required|string|min:3|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:30',
            'city' => 'nullable|string|max:60',
            'office_address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:60',
        ]);

        try {
            $agencyId = 'agy_' . bin2hex(random_bytes(8));
            $slug = Str::slug($request->name);
            if ($slug === '') {
                $slug = 'agency-' . substr($agencyId, 4, 8);
            }
            if (Agency::where('slug', $slug)->exists()) {
                $slug .= '-' . substr($agencyId, -4);
            }

            $result = DB::transaction(function () use ($request, $agencyId, $slug) {
                $user = User::create([
                    'name' => $request->contactName,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'dealer', // legacy-scoped non-platform role
                    'status' => 'active', // login allowed; agency approval gates the workspace
                    'phone' => $request->phone,
                    'last_login_at' => now(),
                ]);

                $agency = Agency::create([
                    'id' => $agencyId,
                    'name' => $request->name,
                    'slug' => $slug,
                    'description' => $request->description,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'office_address' => $request->office_address,
                    'city' => $request->city ?: 'Karachi',
                    'website' => $request->website,
                    'registration_number' => $request->registration_number,
                    'status' => 'pending',
                    'verification_status' => 'unverified',
                    'owner_user_id' => $user->id,
                ]);

                AgencyMember::create([
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                    'role_id' => 'role_agency_owner',
                    'status' => 'active',
                ]);

                // A real subscription row from day one. Storage is not quota-
                // enforced in this release; no existing file is ever removed
                // when this term changes.
                \App\Services\AgencyOnboarding::ensureStarterSubscription($agency, $user->id);

                $token = $user->createToken('auth-token')->plainTextToken;

                return [$user, $agency, $token];
            });

            [$user, $agency, $token] = $result;

            // Email verification link (tracked, not login-blocking; the
            // agency-approval gate remains the workspace gate).
            try {
                $user->notify(VerifyEmailLink::issueFor($user->email));
            } catch (\Throwable $e) {
                Log::warning('Verification mail could not be queued', ['exception' => get_class($e)]);
            }

            $this->writeAudit($request, 'agency.register', 'agency:' . $agency->id, 'Agency self-registered: ' . $agency->name, [
                'agency_id' => $agency->id,
                'entity_type' => 'agency',
                'entity_id' => $agency->id,
            ]);

            Log::info('New agency registration pending review', [
                'agency_id' => $agency->id, 'name' => $agency->name,
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'agency' => $agency->only(['id', 'name', 'slug', 'status', 'verification_status']),
                'user' => app(AuthController::class)->userPayload($user, $agency->id),
            ], 201);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Agency registration failed', ['exception' => get_class($e)]);
            return response()->json([
                'success' => false,
                'message' => 'Agency registration could not be completed.',
            ], 500);
        }
    }

    /**
     * GET /api/agencies — public directory of verified, active agencies.
     */
    public function index(Request $request)
    {
        $query = Agency::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified');

        if ($request->filled('search')) {
            $s = $this->escapeLike((string) $request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->filled('city')) {
            $city = $this->escapeLike((string) $request->city);
            $query->where('city', 'like', "%{$city}%");
        }

        // Optional: only agencies that currently have public listings.
        if ($request->boolean('has_listings')) {
            $query->whereHas('properties', function ($q) {
                $q->publiclyVisible();
            });
        }

        $sort = (string) $request->get('sort', 'name');
        if ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } elseif ($sort === 'listings') {
            $query->withCount(['properties as public_listings_count' => function ($q) {
                $q->publiclyVisible();
            }])->orderByDesc('public_listings_count')->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        $perPage = min(max((int) $request->get('per_page', 12), 1), 50);
        $agencies = $query->paginate($perPage);

        $agencies->getCollection()->transform(function (Agency $agency) {
            return $this->publicAgencyPayload($agency);
        });

        return response()->json([
            'success' => true,
            'agencies' => $agencies,
        ]);
    }

    /**
     * GET /api/agencies/{id|slug} — public profile + approved listings.
     */
    public function show(Request $request, string $id)
    {
        $agency = Agency::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        $payload = $this->publicAgencyPayload($agency);

        // Sale-only marketplace: the public profile has never exposed
        // rental listings, even for legacy internal rental rows.
        $listingsQuery = $agency->properties()->publiclyVisible()
            ->where('listing_purpose', 'sale');

        if ($request->filled('type')) {
            $listingsQuery->where('type', $request->type);
        }

        $saleListings = $agency->properties()->publiclyVisible()->where('listing_purpose', 'sale');

        $payload['stats'] = [
            'public_listings' => (clone $saleListings)->count(),
            'sale_listings' => (clone $saleListings)->count(),
            'rent_listings' => 0,
            'team_members' => $agency->activeMembers()->count(),
        ];

        $payload['listings'] = $listingsQuery
            ->orderByDesc('isFeatured')
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->paginate(min(max((int) $request->get('per_page', 12), 1), 50))
            ->through(fn ($p) => $this->publicListingPayload($p));

        return response()->json([
            'success' => true,
            'agency' => $payload,
        ]);
    }

    private function publicListingPayload(\App\Models\Property $property): array
    {
        $property->makeHidden(['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes', 'deletedBy', 'sellerId', 'buyerId', 'created_by', 'updated_by']);

        $payload = $property->only([
            'id', 'title', 'description', 'pricePKR', 'formattedPrice', 'location', 'phase',
            'sector', 'block', 'sizeSqYards', 'roadWidthFeet', 'type', 'status', 'images',
            'isFeatured', 'bedrooms', 'bathrooms', 'possessionReady', 'mdaApproved',
            'plotNumber', 'fileNumber', 'listing_purpose', 'published_at', 'slug',
            'videoUrl', 'features', 'westOpen', 'cornerPlot', 'parkFacing', 'mainBoulevard',
            'streetNumber', 'frontFeet', 'depthFeet',
            'contactPerson', 'contactPhone', 'contactWhatsApp', 'agencyName', 'agency_id',
        ]);

        $payload['agency'] = $property->relationLoaded('agency') && $property->agency
            ? $property->agency->only(['id', 'name', 'slug'])
            : null;

        return $payload;
    }

    /**
     * GET /api/agency/profile — the acting agency's own profile + stats.
     */
    public function current(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.view');

        $agency = Agency::findOrFail($agencyId);

        $payload = $agency->only([
            'id', 'name', 'slug', 'logo_url', 'cover_url', 'description', 'email',
            'phone', 'whatsapp', 'office_address', 'city', 'registration_number',
            'website', 'status', 'verification_status', 'verified_at', 'created_at',
        ]);

        $payload['stats'] = [
            'members' => $agency->activeMembers()->count(),
            'listings_total' => $agency->properties()->whereNull('deleted_at')->count(),
            'listings_public' => $agency->properties()->publiclyVisible()->count(),
            'listings_pending' => $agency->properties()->where('approval_status', 'pending')->count(),
            'documents' => $agency->documents()->count(),
            'leads' => $agency->inquiries()->count(),
        ];

        $subscription = $agency->subscription()->with('plan')->first();
        if ($subscription) {
            $payload['subscription'] = [
                'status' => $subscription->status,
                'plan' => $subscription->plan?->only(['key', 'name', 'price', 'currency', 'interval']),
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
            ];
        }
        $payload['limits'] = $agency->limits();

        return response()->json([
            'success' => true,
            'agency' => $payload,
        ]);
    }

    /**
     * PUT /api/agency/profile — update the acting agency's profile.
     */
    public function updateCurrent(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.update');

        $request->validate([
            'name' => 'sometimes|string|min:3|max:120',
            'description' => 'nullable|string|max:2000',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'office_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:60',
            'website' => 'nullable|url|max:255',
            'logo_url' => 'nullable|string|max:1024',
            'cover_url' => 'nullable|string|max:1024',
        ]);

        $agency = Agency::findOrFail($agencyId);
        $agency->fill($request->only([
            'name', 'description', 'email', 'phone', 'whatsapp',
            'office_address', 'city', 'website', 'logo_url', 'cover_url',
        ]));
        $agency->save();

        $this->writeAudit($request, 'agency.update', 'agency:' . $agency->id, 'Agency profile updated', [
            'agency_id' => $agency->id,
            'entity_type' => 'agency',
            'entity_id' => $agency->id,
            'metadata' => ['fields' => array_keys($request->only([
                'name', 'description', 'email', 'phone', 'whatsapp',
                'office_address', 'city', 'website', 'logo_url', 'cover_url',
            ]))],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agency profile updated.',
            'agency' => $agency->only(['id', 'name', 'slug', 'email', 'phone', 'whatsapp', 'office_address', 'city', 'website', 'logo_url', 'cover_url', 'description']),
        ]);
    }

    /* ---------------- helpers ---------------- */

    private function publicAgencyPayload(Agency $agency): array
    {
        $publicListings = $agency->properties()->publiclyVisible()
            ->where('listing_purpose', 'sale');

        return [
            'id' => $agency->id,
            'name' => $agency->name,
            'slug' => $agency->slug,
            'logo_url' => $agency->logo_url,
            'cover_url' => $agency->cover_url,
            'description' => $agency->description,
            'email' => $agency->email,
            'phone' => $agency->phone,
            'whatsapp' => $agency->whatsapp,
            'city' => $agency->city,
            'office_address' => $agency->office_address,
            'website' => $agency->website,
            'verification_status' => $agency->verification_status,
            'status' => $agency->status,
            'member_count' => $agency->activeMembers()->count(),
            'listing_count' => (clone $publicListings)->count(),
            'sale_listing_count' => (clone $publicListings)->count(),
            'rent_listing_count' => 0,
            'since' => optional($agency->created_at)->format('Y'),
            'created_at' => optional($agency->created_at)?->toIso8601String(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
