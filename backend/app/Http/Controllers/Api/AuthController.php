<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\User;
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

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Also check dealers table (for dealer accounts)
        if (!$user) {
            $dealer = Dealer::where('email', $request->email)->first();
            if ($dealer) {
                // Create a user record from dealer if needed
                $user = User::firstOrCreate(
                    ['email' => $dealer->email],
                    [
                        'name' => $dealer->fullName,
                        'password' => Hash::make('password'), // Default password for demo
                        'role' => $dealer->role ?? 'dealer',
                        'dealer_id' => $dealer->id,
                    ]
                );
            }
        }

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
                'permissions' => $this->getPermissionsForRole($user->role),
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

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'dealer_id' => $dealer->id,
                'permissions' => $this->getPermissionsForRole('dealer'),
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
                'permissions' => $this->getPermissionsForRole($user->role),
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
     * Get permissions for a role (server-side RBAC).
     */
    private function getPermissionsForRole(string $role): array
    {
        $permissions = [
            'super_admin' => [
                'view_properties', 'edit_properties', 'delete_properties', 'view_private_financials',
                'view_transactions', 'edit_transactions', 'void_transactions', 'manage_dealers',
                'manage_users', 'pay_commissions', 'view_audit', 'manage_projects', 'manage_cms',
                'view_inquiries', 'manage_inquiries', 'upload_media', 'verify_documents', 'manage_sellers',
                'manage_buyers', 'view_plot_locator', 'manage_plot_locator', 'view_dealers', 'view_commissions',
                'export_data', 'manage_settings', 'view_all_reports', 'manage_roles',
            ],
            'admin' => [
                'view_properties', 'edit_properties', 'delete_properties', 'view_private_financials',
                'view_transactions', 'edit_transactions', 'manage_dealers', 'view_audit', 'manage_projects',
                'manage_cms', 'view_inquiries', 'manage_inquiries', 'upload_media', 'verify_documents',
                'manage_sellers', 'manage_buyers', 'view_plot_locator', 'manage_plot_locator', 'view_dealers',
                'view_commissions', 'export_data', 'manage_settings',
            ],
            'manager' => [
                'view_properties', 'view_transactions', 'edit_transactions', 'view_inquiries',
                'manage_inquiries', 'upload_media', 'verify_documents', 'view_plot_locator',
                'view_dealers', 'view_commissions',
            ],
            'dealer' => [
                'view_properties', 'view_my_deals', 'view_my_commission', 'submit_buyer', 'view_dealers',
            ],
            'operator' => [
                'view_properties', 'edit_properties', 'upload_media', 'view_inquiries',
            ],
        ];

        return $permissions[$role] ?? [];
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