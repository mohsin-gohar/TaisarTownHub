<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use App\Models\Dealer;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login with email and password, issue Sanctum token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Issue Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'dealer_id' => $user->dealer_id,
                'permissions' => RolePermissions::forRole($user->role),
            ],
        ]);
    }

    /**
     * Register a new dealer account.
     */
    public function register(Request $request)
    {
        $request->validate([
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'cnic' => 'required|string|max:20',
            'agencyOrBranch' => 'nullable|string|max:255',
            'sectorFocus' => 'nullable|string|max:100',
        ]);

        $defaultAvatars = [
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
            'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80',
            'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=200&q=80',
            'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=200&q=80'
        ];
        $avatar = $defaultAvatars[crc32($request->email) % count($defaultAvatars)];
        $dealerId = 'deal-' . uniqid();
        $agencyName = $request->agencyOrBranch ?: $request->fullName . ' & Associates';
        $sector = $request->sectorFocus ?: 'Sector 63';

        // Create dealer record
        $dealer = Dealer::create([
            'id' => $dealerId,
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'cnic' => $this->maskCnic($request->cnic),
            'cnicMasked' => $this->maskCnic($request->cnic),
            'agencyOrBranch' => $agencyName,
            'agencyAffiliation' => $agencyName,
            'role' => 'dealer',
            'status' => 'Active',
            'avatar' => $avatar,
            'assignedPropertiesCount' => 3,
            'completedDealsCount' => 6,
            'dealsClosed' => 6,
        ]);

        // Create user record
        $user = User::create([
            'name' => $request->fullName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'dealer',
            'dealer_id' => $dealer->id,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Auto-allocate 3 verified Scheme 45 property files to agency stock
        $propId1 = 'prop-' . uniqid();
        $propId2 = 'prop-' . uniqid();
        $propId3 = 'prop-' . uniqid();

        $starterFiles = [
            [
                'id' => $propId1,
                'plotNumber' => 'P-' . rand(100, 999),
                'fileNumber' => 'MDA-70D-' . rand(1000, 9999),
                'title' => '120 Sq. Yd. Prime West-Open Residential Plot',
                'description' => "Verified 120 square yard plot in Sector 70-D, Phase 1. Managed and represented exclusively by {$agencyName}. Complete biometric desk clearance and immediate construction possession.",
                'pricePKR' => 3600000,
                'formattedPrice' => 'PKR 36 Lacs',
                'location' => 'Sector 70-D, Street 12, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 1',
                'sector' => 'Sector 70-D',
                'sizeSqYards' => 120,
                'type' => 'Residential Plot',
                'status' => 'Available',
                'isFeatured' => true,
                'possessionReady' => true,
                'mdaApproved' => true,
                'westOpen' => true,
                'cornerPlot' => false,
                'parkFacing' => false,
                'mainBoulevard' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['West Open', '40ft Carpeted Street', 'MDA Verified File', 'Ready for Construction', 'Biometric Ready'],
                'contactPerson' => $request->fullName,
                'contactPhone' => $request->phone,
                'contactWhatsApp' => $request->phone,
                'assignedDealerId' => $dealer->id,
                'agencyName' => $agencyName,
                'createdDate' => now()->toDateString(),
            ],
            [
                'id' => $propId2,
                'plotNumber' => 'R-' . rand(50, 250),
                'fileNumber' => 'MDA-63-' . rand(1000, 9999),
                'title' => '120 Sq. Yd. 100ft Boulevard Residential Plot',
                'description' => "High elevation residential plot on the main 100ft road in Sector 63, Phase 2. Handled by {$agencyName}. Ideal for residential villa with high investment appreciation.",
                'pricePKR' => 3500000,
                'formattedPrice' => 'PKR 35 Lacs',
                'location' => 'Sector 63, 100ft Boulevard, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 2',
                'sector' => 'Sector 63',
                'sizeSqYards' => 120,
                'type' => 'Residential Plot',
                'status' => 'Hot Deal',
                'isFeatured' => true,
                'possessionReady' => true,
                'mdaApproved' => true,
                'westOpen' => false,
                'cornerPlot' => false,
                'parkFacing' => false,
                'mainBoulevard' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['100ft Boulevard Facing', 'MDA Computerized Allotment', 'Leveled Land', 'Ready for Biometric Transfer'],
                'contactPerson' => $request->fullName,
                'contactPhone' => $request->phone,
                'contactWhatsApp' => $request->phone,
                'assignedDealerId' => $dealer->id,
                'agencyName' => $agencyName,
                'createdDate' => now()->toDateString(),
            ],
            [
                'id' => $propId3,
                'plotNumber' => 'B-' . rand(10, 150),
                'fileNumber' => 'MDA-78-' . rand(1000, 9999),
                'title' => '80 Sq. Yd. Balloted Investment Plot File',
                'description' => "Balloted 80 sq. yd. affordable plot file in Sector 78, Phase 2. Guaranteed clean chain of title with computerized MDA ballot record verified by {$agencyName}.",
                'pricePKR' => 2200000,
                'formattedPrice' => 'PKR 22 Lacs',
                'location' => 'Sector 78, Block B, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 2',
                'sector' => 'Sector 78',
                'sizeSqYards' => 80,
                'type' => 'Residential Plot',
                'status' => 'Available',
                'isFeatured' => false,
                'possessionReady' => false,
                'mdaApproved' => true,
                'westOpen' => true,
                'cornerPlot' => false,
                'parkFacing' => false,
                'mainBoulevard' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['MDA Balloted File', 'Low Entry Investment', 'High ROI Potential', 'Clear Title'],
                'contactPerson' => $request->fullName,
                'contactPhone' => $request->phone,
                'contactWhatsApp' => $request->phone,
                'assignedDealerId' => $dealer->id,
                'agencyName' => $agencyName,
                'createdDate' => now()->toDateString(),
            ]
        ];

        foreach ($starterFiles as $file) {
            \App\Models\Property::updateOrCreate(['id' => $file['id']], $file);
            BusinessRecord::put('properties', $file['id'], $file);
        }

        BusinessRecord::put('dealers', $dealer->id, [
            'id' => $dealer->id,
            'fullName' => $dealer->fullName,
            'email' => $dealer->email,
            'phone' => $dealer->phone,
            'agencyOrBranch' => $dealer->agencyOrBranch,
            'agencyAffiliation' => $dealer->agencyOrBranch,
            'role' => 'dealer',
            'status' => 'Active',
            'avatar' => $avatar,
            'assignedPropertiesCount' => 3,
            'completedDealsCount' => 6,
            'dealsClosed' => 6,
            'totalEarnedPKR' => 0,
            'pendingCommissionPKR' => 0,
            'cnicMasked' => $dealer->cnicMasked,
            'createdAt' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'dealer_id' => $dealer->id,
                'permissions' => RolePermissions::forRole('dealer'),
            ],
        ], 201);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'dealer_id' => $user->dealer_id,
                'permissions' => RolePermissions::forRole($user->role),
            ],
        ]);
    }

    /**
     * Logout - revoke current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Mask CNIC for privacy (server-side).
     */
    private function maskCnic(string $cnic): string
    {
        $clean = preg_replace('/[^0-9]/', '', $cnic);
        if (strlen($clean) < 7) {
            return str_repeat('*', strlen($clean));
        }
        return substr($clean, 0, 5) . str_repeat('*', strlen($clean) - 9) . substr($clean, -4);
    }
}