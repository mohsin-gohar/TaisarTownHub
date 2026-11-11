<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use App\Models\Dealer;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use AuthorizesApi;

    /**
     * Login with email and password, issue Sanctum token.
     * Per-account lockout: after N failed attempts (system setting) the
     * account is locked for M minutes — independent of the shared IP throttle.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Locked account check
        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $minutes = (int) ceil($user->locked_until->diffInMinutes(now()));
            throw ValidationException::withMessages([
                'email' => ["Account temporarily locked. Try again in {$minutes} minute(s)."],
            ]);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Contact support.'],
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            $maxAttempts = (int) SystemSetting::get('security.login_max_attempts', 5);
            $lockoutMinutes = (int) SystemSetting::get('security.login_lockout_minutes', 15);

            $failed = (int) $user->failed_attempts + 1;
            $updates = ['failed_attempts' => $failed];
            if ($failed >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
                $updates['failed_attempts'] = 0;
                Log::warning('Account locked after failed logins', [
                    'user_id' => $user->id, 'ip' => $request->ip(), 'attempts' => $failed,
                ]);
            }
            User::where('id', $user->id)->update($updates);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Success: reset counters
        User::where('id', $user->id)->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Register a new dealer account.
     *
     * NOTE: this used to fabricate 3 "verified" featured properties and fake
     * deal statistics for every registrant. Removed — accounts now start with
     * real (zero) counters and no invented inventory.
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
            'assignedPropertiesCount' => 0,
            'completedDealsCount' => 0,
            'dealsClosed' => 0,
        ]);

        $user = User::create([
            'name' => $request->fullName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'dealer',
            'dealer_id' => $dealer->id,
            'phone' => $request->phone,
            'last_login_at' => now(),
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
            'avatar' => $avatar,
            'assignedPropertiesCount' => 0,
            'completedDealsCount' => 0,
            'dealsClosed' => 0,
            'totalEarnedPKR' => 0,
            'pendingCommissionPKR' => 0,
            'cnicMasked' => $dealer->cnicMasked,
            'createdAt' => now()->toDateString(),
        ]);

        $this->writeAudit($request, 'auth.register', 'user:' . $user->id, 'Dealer account created', [
            'agency_id' => null,
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->userPayload($user->fresh()),
        ], 201);
    }

    /**
     * Get authenticated user info (incl. agency memberships + effective perms).
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $this->userPayload($request->user()),
            'agency_id' => $this->agencyId($request),
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
     * Logout everywhere - revoke all tokens of this user.
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices.',
        ]);
    }

    /**
     * Payload for login/me responses. `permissions` keeps the legacy matrix
     * shape the existing frontend gates expect; `enterprise` adds the
     * membership-scoped keys and `agencies` lets the client pick a context.
     */
    private function userPayload(User $user): array
    {
        $memberships = $user->memberAgencies();
        $defaultAgency = $memberships[0]['agency_id'] ?? null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'dealer_id' => $user->dealer_id,
            'phone' => $user->phone,
            'permissions' => RolePermissions::forRole($user->role),
            'enterprise_permissions' => $user->permissionsFor($defaultAgency),
            'agencies' => $memberships,
            'is_platform' => $memberships === [],
        ];
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
