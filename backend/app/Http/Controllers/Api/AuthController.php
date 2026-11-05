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

        // Create dealer record
        $dealer = Dealer::create([
            'id' => 'deal-' . uniqid(),
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'cnic' => $this->maskCnic($request->cnic),
            'cnicMasked' => $this->maskCnic($request->cnic),
            'agencyOrBranch' => $request->agencyOrBranch,
            'agencyAffiliation' => $request->agencyOrBranch,
            'role' => 'dealer',
            'status' => 'Active',
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

        BusinessRecord::put('dealers', $dealer->id, [
            'id' => $dealer->id,
            'fullName' => $dealer->fullName,
            'email' => $dealer->email,
            'phone' => $dealer->phone,
            'agencyOrBranch' => $dealer->agencyOrBranch,
            'agencyAffiliation' => $dealer->agencyOrBranch,
            'role' => 'dealer',
            'status' => 'Active',
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