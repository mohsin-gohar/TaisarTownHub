<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgencyStaffController extends Controller
{
    use AuthorizesApi;

    /* ================= Members ================= */

    /** GET /api/agency/members */
    public function members(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.view');

        $members = AgencyMember::where('agency_id', $agencyId)
            ->with(['user:id,name,email,phone,status,last_login_at', 'role:id,key,name,scope'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AgencyMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'phone' => $m->user?->phone,
                'user_status' => $m->user?->status,
                'last_login_at' => $m->user?->last_login_at,
                'role_id' => $m->role_id,
                'role_key' => $m->role?->key,
                'role_name' => $m->role?->name,
                'status' => $m->status,
                'extra_permissions' => $m->permissions ?? [],
                'deactivated_at' => $m->deactivated_at,
                'joined_at' => $m->created_at,
            ]);

        return response()->json(['success' => true, 'members' => $members]);
    }

    /** POST /api/agency/members/invite */
    public function invite(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $request->validate([
            'email' => 'required|email|max:255',
            'role_id' => 'required|string|max:64',
        ]);

        $email = mb_strtolower(trim((string) $request->email));
        $role = $this->resolveAgencyRole($agencyId, $request->role_id);

        // Only an existing owner may mint another owner invitation.
        if ($role->id === 'role_agency_owner' && !$this->requesterIsOwner($agencyId, $request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only an agency owner may invite another owner.',
            ], 403);
        }

        $token = Str::random(48);
        $result = DB::transaction(function () use ($agencyId, $email, $role, $request, $token): array {
            // Serialize invitations and acceptances for this agency so two
            // concurrent requests cannot both consume the last staff slot.
            Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();

            if ($role->id === 'role_agency_owner' && !$this->requesterIsOwner($agencyId, $request->user()->id)) {
                return ['error' => 'owner_only'];
            }

            $alreadyMember = User::whereRaw('LOWER(email) = ?', [$email])
                ->whereHas('memberships', fn ($query) => $query
                    ->where('agency_id', $agencyId)
                    ->where('status', 'active'))
                ->exists();

            if ($alreadyMember) {
                return ['error' => 'already_member'];
            }

            $pendingInvite = AgencyInvitation::where('agency_id', $agencyId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingInvite) {
                return ['error' => 'pending_invitation'];
            }

            if ($block = PlanLimits::block(
                Agency::findOrFail($agencyId),
                'staff',
                fn () => PlanLimits::staffUsed($agencyId)
            )) {
                return ['blocked' => $block];
            }

            $invitation = AgencyInvitation::create([
                'agency_id' => $agencyId,
                'email' => $email,
                'role_id' => $role->id,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            return ['invitation' => $invitation];
        }, 3);

        if (isset($result['blocked'])) {
            return PlanLimits::deny('Staff limit reached for your subscription plan. Upgrade to invite more members.', $result['blocked']);
        }

        if (($result['error'] ?? null) === 'owner_only') {
            return response()->json(['success' => false, 'message' => 'Only an agency owner may invite another owner.'], 403);
        }
        if (($result['error'] ?? null) === 'already_member') {
            return response()->json(['success' => false, 'message' => 'This user is already a member of your agency.'], 422);
        }
        if (($result['error'] ?? null) === 'pending_invitation') {
            return response()->json(['success' => false, 'message' => 'A pending invitation already exists for this email. Revoke it before sending another.'], 409);
        }

        /** @var AgencyInvitation $invitation */
        $invitation = $result['invitation'];
        $this->writeAudit($request, 'agency.invite', 'invitation:' . $invitation->id, 'Invited ' . $email . ' as ' . $role->name, [
            'agency_id' => $agencyId,
            'entity_type' => 'agency_invitation',
            'entity_id' => (string) $invitation->id,
            'metadata' => ['email' => $email, 'role' => $role->key],
        ]);

        // No mail provider is configured (MAIL_MAILER=log), so the token is
        // returned to the inviting owner for manual sharing. When email is
        // configured later this becomes a mailed link.
        return response()->json([
            'success' => true,
            'message' => 'Invitation created. Share the invite token with the team member.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role_id' => $invitation->role_id,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'invite_token' => $token,
            ],
        ], 201);
    }

    public function invitations(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $invitations = AgencyInvitation::where('agency_id', $agencyId)
            ->with('role:id,name,key')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AgencyInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role_name' => $i->role?->name,
                'status' => $i->status,
                'expires_at' => $i->expires_at,
                'created_at' => $i->created_at,
            ]);

        return response()->json(['success' => true, 'invitations' => $invitations]);
    }

    /** DELETE /api/agency/invitations/{id} — revoke */
    public function revokeInvitation(Request $request, int $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $result = DB::transaction(function () use ($agencyId, $id): array {
            Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();
            $invitation = AgencyInvitation::where('agency_id', $agencyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($invitation->status !== 'pending') {
                return ['revoked' => false];
            }

            $invitation->status = 'revoked';
            $invitation->save();

            return ['revoked' => true];
        }, 3);

        if (!$result['revoked']) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending invitations can be revoked.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Invitation revoked.']);
    }

    public function acceptInvitation(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:64',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $invitation = AgencyInvitation::where('token_hash', hash('sha256', $request->token))->first();
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or already used invitation token.',
            ], 422);
        }

        $authenticatedUser = null;
        if ($request->bearerToken()) {
            try {
                $authenticatedUser = auth('sanctum')->user();
            } catch (\Throwable $exception) {
                $authenticatedUser = null;
            }
        }

        if (!$authenticatedUser) {
            $request->validate([
                'name' => 'required|string|min:2|max:255',
                'password' => 'required|string|min:8',
            ]);
        } elseif (strtolower($authenticatedUser->email) !== strtolower($invitation->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation was issued to a different email address.',
            ], 403);
        }

        $result = DB::transaction(function () use ($invitation, $authenticatedUser, $request): array {
            // Keep the same lock order as invite/revoke: agency first, then
            // invitation. Expiry is checked again after both locks are held.
            $agency = Agency::whereKey($invitation->agency_id)->lockForUpdate()->firstOrFail();
            $claim = AgencyInvitation::whereKey($invitation->id)->lockForUpdate()->first();

            if (!$claim || $claim->status !== 'pending') {
                return ['error' => 'used'];
            }

            if ($claim->expires_at !== null && $claim->expires_at->isPast()) {
                $claim->status = 'expired';
                $claim->save();

                return ['error' => 'expired'];
            }

            // The pending invitation already occupies one staff slot. Check
            // the post-acceptance usage (active members + other live invites)
            // so a valid invite is not blocked merely because it is at quota.
            $staffUsageAfterAcceptance = max(0, PlanLimits::staffUsed($agency->id) - 1);
            if ($block = PlanLimits::block($agency, 'staff', fn () => $staffUsageAfterAcceptance)) {
                return ['blocked' => $block];
            }

            if ($authenticatedUser === null) {
                $existing = User::whereRaw('LOWER(email) = ?', [strtolower($claim->email)])
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return ['error' => 'account_exists'];
                }

                // Agency roles live only in agency_members.role_id. The base
                // user role must never be promoted to a platform administrator.
                $user = User::create([
                    'name' => strip_tags($request->name),
                    'email' => strtolower($claim->email),
                    'password' => Hash::make($request->password),
                    'role' => 'dealer',
                    'last_login_at' => now(),
                ]);
            } else {
                $user = User::whereKey($authenticatedUser->id)->lockForUpdate()->first();
                if (!$user || strtolower($user->email) !== strtolower($claim->email)) {
                    return ['error' => 'email_mismatch'];
                }
                if ($user->authenticationBlockedReason() !== null) {
                    return ['error' => 'account_blocked'];
                }
            }

            $membership = AgencyMember::updateOrCreate(
                ['agency_id' => $claim->agency_id, 'user_id' => $user->id],
                [
                    'role_id' => $claim->role_id,
                    'status' => 'active',
                    'deactivated_at' => null,
                    'invited_by' => $claim->invited_by,
                ]
            );

            $claim->update(['status' => 'accepted', 'accepted_at' => now()]);

            return ['user' => $user->fresh(), 'membership' => $membership, 'agency' => $agency];
        }, 3);

        if (isset($result['blocked'])) {
            return PlanLimits::deny('Staff limit reached for your subscription plan. Contact the agency owner to upgrade.', $result['blocked']);
        }

        if (isset($result['error'])) {
            $message = match ($result['error']) {
                'expired' => 'This invitation has expired.',
                'account_exists' => 'An account with this email already exists — please sign in first.',
                'email_mismatch' => 'This invitation was issued to a different email address.',
                'account_blocked' => 'This account cannot accept invitations right now.',
                default => 'Invalid or already used invitation token.',
            };
            $status = $result['error'] === 'email_mismatch' ? 403 : 422;

            return response()->json(['success' => false, 'message' => $message], $status);
        }

        /** @var User $user */
        $user = $result['user'];
        /** @var AgencyMember $membership */
        $membership = $result['membership'];
        $agency = $result['agency'];

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->writeAudit($request, 'agency.invite.accept', 'user:' . $user->id, 'Joined agency ' . $agency->id, [
            'agency_id' => $agency->id,
            'entity_type' => 'agency_member',
            'entity_id' => (string) $membership->id,
        ]);

        $fresh = $user->fresh();

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'Invitation accepted. You now belong to ' . $agency->name . '.',
            'user' => [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'email' => $fresh->email,
                'role' => $fresh->role,
                'dealer_id' => $fresh->dealer_id,
                'permissions' => \App\Support\RolePermissions::forRole($fresh->role),
                'enterprise_permissions' => $fresh->permissionsFor($agency->id),
                'agencies' => $fresh->memberAgencies(),
                'is_platform' => $fresh->isPlatformUser(),
            ],
        ]);
    }

    /**
     * PATCH /api/agency/members/{id} — change role / extra permissions / status.
     */
    public function updateMember(Request $request, int $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $request->validate([
            'role_id' => 'sometimes|string|max:64',
            'status' => 'sometimes|in:active,deactivated',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|max:100',
        ]);

        return DB::transaction(function () use ($request, $agencyId, $id) {
            // Serialize owner changes on the agency row. Without this lock,
            // two concurrent requests could each see two owners and remove
            // one, leaving the agency with no active owner.
            Agency::where('id', $agencyId)->lockForUpdate()->firstOrFail();
            $member = AgencyMember::where('agency_id', $agencyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $payload = [];

            if ($request->has('role_id')) {
                $role = $this->resolveAgencyRole($agencyId, $request->role_id);
                $requesterIsOwner = $this->requesterIsOwner($agencyId, $request->user()->id);
                if (($role->id === 'role_agency_owner' || $member->role_id === 'role_agency_owner')
                    && !$requesterIsOwner) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only an agency owner may change owner membership.',
                    ], 403);
                }
                $this->assertCanGrantPermissions($request, $agencyId, $role->permissions ?? []);
                if ($member->role_id === 'role_agency_owner' && $role->id !== 'role_agency_owner'
                    && $member->status === 'active' && $this->activeOwnerCount($agencyId) <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An agency must keep at least one active owner.',
                    ], 422);
                }
                $payload['role_id'] = $role->id;
            }

            if ($request->has('status')) {
                if ($member->role_id === 'role_agency_owner' && $request->status === 'deactivated'
                    && !$this->requesterIsOwner($agencyId, $request->user()->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only an agency owner may deactivate an owner membership.',
                    ], 403);
                }
                if ($member->role_id === 'role_agency_owner' && $member->status === 'active'
                    && $request->status === 'deactivated' && $this->activeOwnerCount($agencyId) <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An agency must keep at least one active owner.',
                    ], 422);
                }
                $payload['status'] = $request->status;
                $payload['deactivated_at'] = $request->status === 'deactivated' ? now() : null;
            }

            if ($request->has('permissions')) {
                $invalid = array_diff($request->permissions, Permissions::agencyAssignable());
                if ($invalid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Platform-level permissions cannot be granted: ' . implode(', ', $invalid),
                    ], 422);
                }
                $this->assertCanGrantPermissions($request, $agencyId, $request->permissions);
                $payload['permissions'] = array_values($request->permissions);
            }

            $member->update($payload);

            $this->writeAudit($request, 'agency.member.update', 'member:' . $member->id, 'Member updated', [
                'agency_id' => $agencyId,
                'entity_type' => 'agency_member',
                'entity_id' => (string) $member->id,
                'metadata' => $payload,
            ]);

            return response()->json(['success' => true, 'message' => 'Member updated.']);
        }, 3);
    }

    /** DELETE /api/agency/members/{id} — deactivate (never hard-delete). */
    public function removeMember(Request $request, int $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        return DB::transaction(function () use ($request, $agencyId, $id) {
            Agency::where('id', $agencyId)->lockForUpdate()->firstOrFail();
            $member = AgencyMember::where('agency_id', $agencyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($member->role_id === 'role_agency_owner'
                && !$this->requesterIsOwner($agencyId, $request->user()->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only an agency owner may remove an owner membership.',
                ], 403);
            }
            if ($member->role_id === 'role_agency_owner' && $member->status === 'active'
                && $this->activeOwnerCount($agencyId) <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'An agency must keep at least one active owner.',
                ], 422);
            }

            $member->update(['status' => 'deactivated', 'deactivated_at' => now()]);

            $this->writeAudit($request, 'agency.member.remove', 'member:' . $member->id, 'Member deactivated', [
                'agency_id' => $agencyId,
                'entity_type' => 'agency_member',
                'entity_id' => (string) $member->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Member deactivated.']);
        }, 3);
    }

    /* ================= Roles ================= */

    /** GET /api/agency/roles — system + custom roles for this agency. */
    public function roles(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.view');

        $roles = Role::where('scope', 'agency')
            ->where(function ($q) use ($agencyId) {
                $q->whereNull('agency_id')->orWhere('agency_id', $agencyId);
            })
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'key' => $r->key,
                'name' => $r->name,
                'is_system' => $r->is_system,
                'is_custom' => !$r->is_system,
                'permissions' => $r->permissions ?? [],
                'member_count' => AgencyMember::where('role_id', $r->id)->where('agency_id', $agencyId)->count(),
            ]);

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'assignable_permissions' => Permissions::agencyAssignable(),
        ]);
    }

    /** POST /api/agency/roles — create a custom agency role. */
    public function storeRole(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $request->validate([
            'name' => 'required|string|min:2|max:60',
            'key' => 'required|string|min:2|max:60|regex:/^[a-z0-9_]+$/',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|max:100',
        ]);

        $invalid = array_diff($request->permissions, Permissions::agencyAssignable());
        if ($invalid) {
            return response()->json([
                'success' => false,
                'message' => 'Platform-level permissions cannot be granted to agency roles: ' . implode(', ', $invalid),
            ], 422);
        }
        $this->assertCanGrantPermissions($request, $agencyId, $request->permissions);

        if (Role::where('scope', 'agency')->where('key', $request->key)
            ->where(function ($q) use ($agencyId) {
                $q->whereNull('agency_id')->orWhere('agency_id', $agencyId);
            })->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A role with this key already exists.',
            ], 422);
        }

        $role = Role::create([
            'id' => 'rol_' . bin2hex(random_bytes(8)),
            'scope' => 'agency',
            'agency_id' => $agencyId,
            'key' => $request->key,
            'name' => $request->name,
            'is_system' => false,
            'permissions' => array_values($request->permissions),
            'created_by' => $request->user()->id,
        ]);

        $this->writeAudit($request, 'agency.role.create', 'role:' . $role->id, 'Custom role created: ' . $role->name, [
            'agency_id' => $agencyId,
            'entity_type' => 'role',
            'entity_id' => $role->id,
            'metadata' => ['permissions' => $role->permissions],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created.',
            'role' => ['id' => $role->id, 'key' => $role->key, 'name' => $role->name, 'permissions' => $role->permissions],
        ], 201);
    }

    /** PUT /api/agency/roles/{id} — update a custom role. */
    public function updateRole(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $role = Role::where('scope', 'agency')
            ->where('agency_id', $agencyId)
            ->where('is_system', false)
            ->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|min:2|max:60',
            'permissions' => 'sometimes|array|min:1',
            'permissions.*' => 'string|max:100',
        ]);

        if ($request->has('permissions')) {
            $invalid = array_diff($request->permissions, Permissions::agencyAssignable());
            if ($invalid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Platform-level permissions cannot be granted: ' . implode(', ', $invalid),
                ], 422);
            }
            $this->assertCanGrantPermissions($request, $agencyId, $request->permissions);
            $role->permissions = array_values($request->permissions);
        }
        if ($request->has('name')) {
            $role->name = $request->name;
        }
        $role->save();

        $this->writeAudit($request, 'agency.role.update', 'role:' . $role->id, 'Custom role updated: ' . $role->name, [
            'agency_id' => $agencyId,
            'entity_type' => 'role',
            'entity_id' => $role->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Role updated.']);
    }

    /** DELETE /api/agency/roles/{id} — delete an unused custom role. */
    public function destroyRole(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $role = Role::where('scope', 'agency')
            ->where('agency_id', $agencyId)
            ->where('is_system', false)
            ->findOrFail($id);

        $inUse = AgencyMember::where('role_id', $role->id)->exists();
        $pendingInvites = AgencyInvitation::where('role_id', $role->id)
            ->where('status', 'pending')->exists();

        if ($inUse || $pendingInvites) {
            return response()->json([
                'success' => false,
                'message' => 'Role is still assigned to members or pending invitations.',
            ], 422);
        }

        $role->delete();

        $this->writeAudit($request, 'agency.role.delete', 'role:' . $id, 'Custom role deleted', [
            'agency_id' => $agencyId,
            'entity_type' => 'role',
            'entity_id' => $id,
        ]);

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    /* ================= helpers ================= */

    /** Resolve a role id belonging to this agency's system roles or its own custom roles. */
    private function resolveAgencyRole(string $agencyId, string $roleId): Role
    {
        $role = Role::where('id', $roleId)
            ->where('scope', 'agency')
            ->where(function ($q) use ($agencyId) {
                $q->whereNull('agency_id')->orWhere('agency_id', $agencyId);
            })
            ->first();

        if (!$role) {
            abort(422, 'Unknown role for this agency.');
        }

        return $role;
    }

    /**
     * A staff manager may delegate only permissions they already hold.
     * Otherwise a manager could create/assign a custom role to self and
     * acquire owner-only billing or settings access. Active agency owners
     * remain the authority for granting any agency-scoped capability.
     */
    private function assertCanGrantPermissions(Request $request, string $agencyId, array $permissions): void
    {
        if ($this->requesterIsOwner($agencyId, (int) $request->user()->id)) {
            return;
        }

        $held = $request->user()->permissionsFor($agencyId);
        $excess = array_values(array_diff(array_unique($permissions), $held));
        if ($excess !== []) {
            abort(403, 'You cannot grant agency permissions you do not hold: ' . implode(', ', $excess));
        }
    }

    private function activeOwnerCount(string $agencyId): int
    {
        return AgencyMember::where('agency_id', $agencyId)
            ->where('role_id', 'role_agency_owner')
            ->where('status', 'active')
            ->count();
    }

    /** True only for an active, agency-scoped owner membership (never a global role). */
    private function requesterIsOwner(string $agencyId, int $userId): bool
    {
        return AgencyMember::where('agency_id', $agencyId)
            ->where('user_id', $userId)
            ->where('role_id', 'role_agency_owner')
            ->where('status', 'active')
            ->exists();
    }
}



