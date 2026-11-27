<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\SystemSetting;
use App\Support\MfaVerification;
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

        $routeUri = $request->route()?->uri ?? '';

        // The verification endpoint itself is always allowed. Users who are
        // required to enroll but have not yet enabled MFA must also be able to
        // inspect status, start setup and confirm the first authenticator code.
        // Once enrolled, these routes are protected like every other endpoint.
        if (str_ends_with($routeUri, 'auth/mfa/verify')) {
            return $next($request);
        }
        if (!$user->hasMfaEnabled() && (
            str_ends_with($routeUri, 'auth/mfa/status')
            || str_ends_with($routeUri, 'auth/mfa/setup')
            || str_ends_with($routeUri, 'auth/mfa/enable')
        )) {
            return $next($request);
        }

        if (!$this->mfaRequiredFor($user, $request)) {
            return $next($request);
        }

        // Verification is bound to the exact token used for this request.
        // A user-wide stamp let any other token (e.g. one issued before MFA
        // enrolment or via invitation acceptance) bypass the second factor.
        $token = $request->user()->currentAccessToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Multi-factor authentication is required.',
                'mfa_required' => true,
            ], 403);
        }
        if (MfaVerification::isVerified((int) $user->id, (int) $token->id)) {
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

        // Platform roles always require MFA under the global policy.
        if (in_array($user->role, ['super_admin', 'admin', 'manager'], true)) {
            return true;
        }

        // Agency authority lives in active memberships, NOT users.role.
        // Agency owner / manager with an active membership require MFA.
        if ($user->memberships()
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->whereIn('key', ['agency_owner', 'agency_manager']))
            ->exists()) {
            return true;
        }

        // Custom agency roles with elevated write/manage permissions also
        // require MFA under the global admin policy so privileged custom
        // roles cannot bypass mandatory second-factor verification.
        $elevatedAgencyKeys = [
            'agency.manage_staff', 'agency.manage_settings', 'agency.subscribe',
            'documents.manage_permissions', 'finance.manage_payments',
            'properties.publish', 'properties.delete', 'crm.delete_lead',
        ];
        // hasPermission() without an agency id only consults platform grants,
        // so custom agency roles must be checked in the context of each active
        // membership. Otherwise a privileged custom role bypasses this policy.
        $memberships = $user->memberships()
            ->where('status', 'active')
            ->pluck('agency_id');
        foreach ($memberships as $membershipAgencyId) {
            foreach ($elevatedAgencyKeys as $key) {
                if ($user->hasPermission($key, (string) $membershipAgencyId)) {
                    return true;
                }
            }
        }

        return false;
    }
}
