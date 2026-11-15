<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve tenant context from current memberships and enforce workspace approval.
 * Public routes remain public; auth status and token revocation remain usable
 * even after approval or membership is withdrawn. Platform cross-agency access
 * requires an explicit platform grant and is flagged for controller auditing.
 */
class AgencyContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $requested = $request->header('X-Agency-Id');
            $access = $user->workspaceAccess($requested);
            $request->attributes->set('workspace_access', $access);

            $protected = in_array('auth:sanctum', $request->route()?->gatherMiddleware() ?? [], true);
            $statusOrLogout = $request->is('api/auth/me', 'api/auth/logout', 'api/auth/logout-all');

            if ($protected && !$statusOrLogout && !$access['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $access['message'],
                    'workspace_access' => $access,
                ], 403);
            }

            // Denied users may browse public content, but must not inherit a
            // privileged tenant view on mixed public/authenticated read routes.
            $agencyId = ($access['allowed'] || $statusOrLogout)
                ? $user->resolveAgencyId($requested) : null;
            $crossAgency = $access['allowed'] && $agencyId !== null
                && $user->hasPermission('admin.manage_agencies');

            $request->attributes->set('agency_id', $agencyId);
            $request->attributes->set('agency_cross_agency', $crossAgency);
        }

        return $next($request);
    }
}
