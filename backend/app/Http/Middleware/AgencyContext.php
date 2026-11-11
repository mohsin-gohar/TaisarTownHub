<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the acting agency for the request — the ONLY source of which
 * agency a user acts for is their own active agency_members row.
 *
 * - `X-Agency-Id` header: for members it must match one of their own active
 *   memberships, otherwise the request is denied (403). For platform users it
 *   may name any agency, but only with `admin.manage_agencies`, and the
 *   request is flagged as cross-agency for audit.
 * - Without the header, members default to their first active membership;
 *   platform users act platform-wide (agency_id = null).
 *
 * The resolved value is stored on the request attributes as `agency_id`
 * and never taken from request bodies.
 */
class AgencyContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $requested = $request->header('X-Agency-Id');
            $memberships = $user->activeMemberships()->get();

            if ($memberships->isNotEmpty()) {
                if ($requested !== null && $requested !== '') {
                    if (!$memberships->contains('agency_id', $requested)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Access denied for the requested agency.',
                        ], 403);
                    }
                    $agencyId = $requested;
                } else {
                    $agencyId = $memberships->first()->agency_id;
                }
                $crossAgency = false;
            } else {
                // Platform-level user (no memberships).
                if ($requested !== null && $requested !== '') {
                    if (!$user->hasPermission('admin.manage_agencies')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Access denied for the requested agency.',
                        ], 403);
                    }
                    $agencyId = $requested;
                    $crossAgency = true;
                } else {
                    $agencyId = null;
                    $crossAgency = false;
                }
            }

            $request->attributes->set('agency_id', $agencyId);
            $request->attributes->set('agency_cross_agency', $crossAgency);
        }

        return $next($request);
    }
}
