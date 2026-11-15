<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Enforce multi-factor authentication on the backend.
 *
 * When MFA is required for the acting user (either because they personally
 * have it enabled, or because a global policy requires it for agency admins),
 * every authenticated request must be accompanied by a recent MFA
 * verification. Successful MFA verification (POST /api/auth/mfa/verify)
 * stamps a short-lived cache marker; this middleware rejects requests that
 * lack a fresh stamp so MFA can never be bypassed on the backend.
 *
 * Routes under /auth/* (login, register, mfa/verify, ...) are excluded so
 * users can authenticate and complete MFA.
 */
class EnsureMfaVerified
{
    public const MFA_VERIFIED_TTL_SECONDS = 24 * 60 * 60; // 24 hours

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Unauthenticated requests are handled by auth:sanctum elsewhere.
        if (!$user) {
            return $next($request);
        }

        // MFA verification endpoint itself is always allowed.
        if ($request->route() && str_ends_with($request->route()->uri, 'auth/mfa/verify')) {
            return $next($request);
        }

        if (!$this->mfaRequiredFor($user, $request)) {
            return $next($request);
        }

        $cacheKey = 'mfa_verified:' . $user->id;
        if (Cache::has($cacheKey)) {
            return $next($request);
        }

        Log::info('MFA enforcement: request blocked pending MFA verification', [
            'user_id' => $user->id,
            'agency_id' => $request->attributes->get('agency_id'),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Multi-factor authentication is required.',
            'mfa_required' => true,
        ], 403);
    }

    /**
     * Determine whether MFA is required for the acting user.
     */
    private function mfaRequiredFor(User $user, Request $request): bool
    {
        // Per-account MFA: the user enrolled a second factor.
        if ($user->hasMfaEnabled()) {
            return true;
        }

        // Global policy: MFA mandatory for agency admins (and above).
        $requireForAdmins = (bool) SystemSetting::get(
            'security.mfa_required_for_admins',
            false
        );
        if (!$requireForAdmins) {
            return false;
        }

        return in_array($user->role, ['super_admin', 'admin', 'manager'], true);
    }
}
