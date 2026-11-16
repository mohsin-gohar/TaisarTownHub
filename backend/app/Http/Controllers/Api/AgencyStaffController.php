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

        $agency = Agency::findOrFail($agencyId);

        // G-8: staff quota (active members + pending invitations) enforced server-side.
        if ($block = PlanLimits::block($agency, 'staff', fn () => PlanLimits::staffUsed($agencyId))) {
            return PlanLimits::deny('Staff limit reached for your subscription plan. Upgrade to invite more members.', $block);
        }

        $role = $this->resolveAgencyRole($agencyId, $request->role_id);

        if (User::where('email', $request->email)->exists()
            && AgencyMember::where('agency_id', $agencyId)
                ->whereHas('user', fn ($q) => $q->where('email', $request->email))
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a member of your agency.',
            ], 422);
        }

        $token = Str::random(48);
        $invitation = AgencyInvitation::create([
            'agency_id' => $agencyId,
            'email' => $request->email,
            'role_id' => $role->id,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $request->user()->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->writeAudit($request, 'agency.invite', 'invitation:' . $invitation->id, 'Invited ' . $request->email . ' as ' . $role->name, [
            'agency_id' => $agencyId,
            'entity_type' => 'agency_invitation',
            'entity_id' => (string) $invitation->id,
            'metadata' => ['email' => $request->email, 'role' => $role->key],
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
                'role_name' => $role->name,
                'expires_at' => $invitation->expires_at,
                'invite_token' => $token,
            ],
        ], 201);
    }

    /** GET /api/agency/invitations */
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

        $invitation = AgencyInvitation::where('agency_id', $agencyId)->findOrFail($id);
        $invitation->update(['status' => 'revoked']);

        $this->writeAudit($request, 'agency.invite.revoke', 'invitation:' . $id, 'Invitation revoked', [
            'agency_id' => $agencyId,
        ]);

        return response()->json(['success' => true, 'message' => 'Invitation revoked.']);
    }

    /**
     * POST /api/agency/invitations/accept — accept an invite.
     * Authenticated users join directly; new users may register through the
     * invite by supplying name + password with the token.
     */
    public function acceptInvitation(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:64',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $invitation = AgencyInvitation::where('token_hash', hash('sha256', $request->token))
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or already used invitation token.',
            ], 422);
        }
        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired.',
            ], 422);
        }

        $user = null;
        if ($request->bearerToken()) {
            try {
                $user = auth('sanctum')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

        if (!$user) {
            // Register through invitation
            $request->validate([
                'name' => 'required|string|min:2|max:255',
                'password' => 'required|string|min:8',
            ]);

            if (User::where('email', $invitation->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account with this email already exists — please sign in first.',
                ], 422);
            }

            $inviteRole = Role::find($invitation->role_id);
            $mappedRole = 'operator';
            if ($inviteRole) {
                $key = $inviteRole->key ?? '';
                $mappedRole = match (true) {
                    str_contains($key, 'owner') => 'admin',
                    str_contains($key, 'manager') => 'manager',
                    str_contains($key, 'agent') || str_contains($key, 'dealer') => 'dealer',
                    default => 'operator',
                };
            }
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'role' => $mappedRole,
                'last_login_at' => now(),
            ]);
        } elseif (strtolower($user->email) !== strtolower($invitation->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation was issued to a different email address.',
            ], 403);
        }

        $membership = DB::transaction(function () use ($invitation, $user) {
            $membership = AgencyMember::updateOrCreate(
                ['agency_id' => $invitation->agency_id, 'user_id' => $user->id],
                [
                    'role_id' => $invitation->role_id,
                    'status' => 'active',
                    'deactivated_at' => null,
                    'invited_by' => $invitation->invited_by,
                ]
            );
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            return $membership;
        });

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->writeAudit($request, 'agency.invite.accept', 'user:' . $user->id, 'Joined agency ' . $invitation->agency_id, [
            'agency_id' => $invitation->agency_id,
            'entity_type' => 'agency_member',
            'entity_id' => (string) $membership->id,
        ]);

        $fresh = $user->fresh();

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'Invitation accepted. You now belong to ' . ($invitation->agency?->name ?? 'the agency') . '.',
            'user' => [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'email' => $fresh->email,
                'role' => $fresh->role,
                'dealer_id' => $fresh->dealer_id,
                'permissions' => \App\Support\RolePermissions::forRole($fresh->role),
                'enterprise_permissions' => $fresh->permissionsFor($invitation->agency_id),
                'agencies' => $fresh->memberAgencies(),
                'is_platform' => false,
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

        $member = AgencyMember::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'role_id' => 'sometimes|string|max:64',
            'status' => 'sometimes|in:active,deactivated',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|max:100',
        ]);

        $payload = [];

        if ($request->has('role_id')) {
            $role = $this->resolveAgencyRole($agencyId, $request->role_id);
            // Privilege escalation guard: only an existing owner may promote to
            // or demote from the owner role.
            $requesterIsOwner = AgencyMember::where('agency_id', $agencyId)
                ->where('user_id', $request->user()->id)
                ->where('role_id', 'role_agency_owner')
                ->where('status', 'active')
                ->exists();
            if (($role->id === 'role_agency_owner' || $member->role_id === 'role_agency_owner')
                && !$requesterIsOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only an agency owner may change owner membership.',
                ], 403);
            }
            // Never demote the last active owner
            if ($member->role_id === 'role_agency_owner' && $role->id !== 'role_agency_owner'
                && $this->activeOwnerCount($agencyId) <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'An agency must keep at least one active owner.',
                ], 422);
            }
            $payload['role_id'] = $role->id;
        }

        if ($request->has('status')) {
            if ($member->role_id === 'role_agency_owner' && $request->status === 'deactivated'
                && $this->activeOwnerCount($agencyId) <= 1) {
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
    }

    /** DELETE /api/agency/members/{id} — deactivate (never hard-delete). */
    public function removeMember(Request $request, int $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.manage_staff');

        $member = AgencyMember::where('agency_id', $agencyId)->findOrFail($id);

        if ($member->role_id === 'role_agency_owner' && $this->activeOwnerCount($agencyId) <= 1) {
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

    private function activeOwnerCount(string $agencyId): int
    {
        return AgencyMember::where('agency_id', $agencyId)
            ->where('role_id', 'role_agency_owner')
            ->where('status', 'active')
            ->count();
    }
}



