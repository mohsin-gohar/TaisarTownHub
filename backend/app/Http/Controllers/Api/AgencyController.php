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

                $token = $user->createToken('auth-token')->plainTextToken;

                return [$user, $agency, $token];
            });

            [$user, $agency, $token] = $result;

            // Email verification link (tracked, not login-blocking; the
            // agency-approval gate remains the workspace gate).
            try {
                $user->notify(VerifyEmailLink::issueFor($user->email));
            } catch (\Throwable $e) {
                Log::warning('Verification mail could not be queued', ['error' => $e->getMessage()]);
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
            Log::error('Agency registration failed', ['error' => $e->getMessage()]);
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
            $s = $this->escapeLike($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 12), 50);
        $agencies = $query->orderBy('name')->paginate($perPage);

        $agencies->getCollection()->transform(function (Agency $agency) {
            return $this->publicAgencyPayload($agency);
        });

        return response()->json([
            'success' => true,
            'agencies' => $agencies,
        ]);
    }

    /**
     * GET /api/agencies/{id} — public profile + approved listings.
     */
    public function show(Request $request, string $id)
    {
        $agency = Agency::where('id', $id)
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->firstOrFail();

        $payload = $this->publicAgencyPayload($agency);

        $payload['listings'] = $agency->properties()
            ->publiclyVisible()
            ->orderByDesc('published_at')
            ->paginate((int) $request->get('per_page', 12))
            ->through(fn ($p) => $p->only([
                'id', 'title', 'pricePKR', 'formattedPrice', 'location', 'phase',
                'sector', 'block', 'sizeSqYards', 'type', 'status', 'images',
                'isFeatured', 'bedrooms', 'bathrooms', 'possessionReady',
                'mdaApproved', 'plotNumber', 'listing_purpose', 'published_at',
            ]));

        return response()->json([
            'success' => true,
            'agency' => $payload,
        ]);
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
            'website', 'status', 'verification_status', 'verification_notes',
            'owner_user_id', 'verified_at', 'created_at',
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
        return [
            'id' => $agency->id,
            'name' => $agency->name,
            'slug' => $agency->slug,
            'logo_url' => $agency->logo_url,
            'cover_url' => $agency->cover_url,
            'description' => $agency->description,
            'phone' => $agency->phone,
            'whatsapp' => $agency->whatsapp,
            'city' => $agency->city,
            'office_address' => $agency->office_address,
            'website' => $agency->website,
            'verification_status' => $agency->verification_status,
            'member_count' => $agency->activeMembers()->count(),
            'listing_count' => $agency->properties()->publiclyVisible()->count(),
            'since' => optional($agency->created_at)->format('Y'),
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
