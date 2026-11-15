<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait AuthorizesApi
{
    /**
     * The acting agency resolved by the AgencyContext middleware.
     * Never read from request bodies — only from the resolved membership.
     */
    protected function agencyId(Request $request): ?string
    {
        return $request->attributes->get('agency_id');
    }

    /** True when a platform user explicitly scoped themselves into an agency. */
    protected function isCrossAgency(Request $request): bool
    {
        return (bool) $request->attributes->get('agency_cross_agency');
    }

    /**
     * Require a permission, scoped to the acting agency when the user is an
     * agency member (role grants) and platform-wide when they are not.
     *
     * Cross-agency platform users (flagged by AgencyContext when they act via
     * `X-Agency-Id` with `admin.manage_agencies`) keep their PLATFORM-WIDE
     * grants on top of the agency context — without this they could not act
     * on behalf of any agency at all.
     */
    protected function requirePermission(Request $request, string $permission): void
    {
        if (!$request->user()) {
            abort(401, 'Authentication required.');
        }

        if (!$this->can($request, $permission)) {
            abort(403, 'Permission denied.');
        }
    }

    /**
     * Non-aborting capability check for response flags and record scoping.
     * Must mirror requirePermission() so the UI never hides an action the
     * API would accept (or shows one it would reject).
     */
    protected function can(Request $request, string $permission): bool
    {
        $user = $request->user();
        if (!$user || ($request->attributes->get('workspace_access')['allowed'] ?? true) === false) {
            return false;
        }

        if ($user->hasPermission($permission, $this->agencyId($request))) {
            return true;
        }

        return $this->isCrossAgency($request) && $user->hasPermission($permission);
    }

    /** Require at least one of the given permissions (agency-scoped). */
    protected function requireAny(Request $request, array $permissions): void
    {
        if (!$request->user()) {
            abort(401, 'Authentication required.');
        }

        foreach ($permissions as $permission) {
            if ($this->can($request, $permission)) {
                return;
            }
        }

        abort(403, 'Permission denied.');
    }

    /**
     * Abort unless the acting agency context is present (agency members).
     * Platform users without an explicit agency scope also fail this check —
     * use it for endpoints that only make sense inside one agency.
     */
    protected function requireAgency(Request $request): string
    {
        $agencyId = $this->agencyId($request);
        if ($agencyId === null || $agencyId === '') {
            abort(403, 'No agency context for this action.');
        }

        return $agencyId;
    }

    protected function writeAudit(
        Request $request,
        string $action,
        string $target,
        string $detail,
        array $extra = []
    ): void {
        $user = $request->user();
        $row = [
            'actor' => $user ? $user->name : 'anonymous',
            'role' => $user ? $user->role : 'none',
            'action' => $action,
            'target' => $target,
            'detail' => $detail,
            'ip' => $request->ip(),
            'created_at' => now(),
            'agency_id' => $extra['agency_id'] ?? $request->attributes->get('agency_id'),
            'user_id' => $user ? $user->id : null,
            'entity_type' => $extra['entity_type'] ?? null,
            'entity_id' => $extra['entity_id'] ?? null,
            'metadata' => $extra['metadata'] ?? null,
            'cross_agency' => (bool) ($extra['cross_agency']
                ?? $request->attributes->get('agency_cross_agency')
                ?? false),
        ];

        Log::info('AUDIT', $row);

        try {
            AuditLog::create($row);
        } catch (\Throwable $e) {
            Log::warning('Audit table write failed', ['error' => $e->getMessage()]);
        }
    }
}
