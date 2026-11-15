<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Platform user administration (admin.manage_users). */
class AdminUserController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_users');

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $page = $query->withCount('memberships')
            ->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        $page->through(function (User $u) {
            $payload = $u->toArray();
            unset($payload['password'], $payload['remember_token']);
            $payload['agencies'] = $u->memberAgencies();
            $payload['locked'] = $u->locked_until !== null && $u->locked_until->isFuture();
            return $payload;
        });

        return response()->json([
            'success' => true,
            'users' => $page->items(),
            'roles' => Role::where('scope', 'platform')->pluck('key'),
            'legacy_roles' => array_keys(\App\Support\RolePermissions::MATRIX),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_users');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'role' => ['required', Rule::in(array_keys(\App\Support\RolePermissions::MATRIX))],
            'phone' => 'nullable|string|max:30',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
        ]);

        $this->writeAudit($request, 'user.create', 'user:' . $user->id, "User created: {$user->email} ({$user->role})", [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'cross_agency' => true,
            'metadata' => ['role' => $user->role],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'phone', 'status', 'created_at']),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->requirePermission($request, 'admin.manage_users');

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(array_keys(\App\Support\RolePermissions::MATRIX))],
            'phone' => 'nullable|string|max:30',
            'status' => 'sometimes|in:active,suspended',
            'password' => 'nullable|string|min:8',
        ]);

        $payload = $request->only(['name', 'email', 'role', 'phone', 'status']);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->password);
        }

        // Never allow suspending/demoting yourself
        if ($user->id === $request->user()->id && (($payload['status'] ?? '') === 'suspended' || ($payload['role'] ?? $user->role) !== $user->role)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own role or suspend your own account.',
            ], 422);
        }

        $user->fill($payload);
        $user->save();

        if (($payload['status'] ?? '') === 'suspended') {
            $user->tokens()->delete();
        }

        // A platform-side password reset is a security event the account owner
        // must know about (Phase 6 — password change notifications).
        if ($request->filled('password')) {
            $user->tokens()->delete();
            $user->notify(new \App\Notifications\PasswordChangedNotification($request->ip(), true));
        }

        $this->writeAudit($request, 'user.update', 'user:' . $user->id, "User updated: {$user->email}", [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'cross_agency' => true,
            'metadata' => ['fields' => array_keys($payload)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'phone', 'status', 'last_login_at']),
        ]);
    }

    /** POST /api/admin/users/{id}/unlock — clear lockout & failed attempts. */
    public function unlock(Request $request, int $id)
    {
        $this->requirePermission($request, 'admin.manage_users');

        $user = User::findOrFail($id);
        $user->failed_attempts = 0;
        $user->locked_until = null;
        $user->save();

        $this->writeAudit($request, 'user.unlock', 'user:' . $user->id, "Lockout cleared: {$user->email}", [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'cross_agency' => true,
        ]);

        return response()->json(['success' => true, 'message' => 'Account unlocked.']);
    }

    /** POST /api/admin/users/{id}/revoke-tokens — force global logout. */
    public function revokeTokens(Request $request, int $id)
    {
        $this->requirePermission($request, 'admin.manage_users');

        $user = User::findOrFail($id);
        $count = $user->tokens()->delete();

        $this->writeAudit($request, 'user.revoke_tokens', 'user:' . $user->id, "Revoked {$count} token(s): {$user->email}", [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'cross_agency' => true,
            'metadata' => ['tokens' => $count],
        ]);

        return response()->json(['success' => true, 'message' => "Revoked {$count} session token(s)."]);
    }

    /** GET /api/admin/roles — role catalog with grant counts. */
    public function roles(Request $request)
    {
        $this->requireAny($request, ['admin.manage_roles', 'admin.manage_users']);

        $roles = Role::withCount('permissionsCatalog')
            ->orderByDesc('scope')
            ->orderBy('key')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'scope' => $r->scope,
                'agency_id' => $r->agency_id,
                'key' => $r->key,
                'name' => $r->name,
                'is_system' => $r->is_system,
                'permissions' => $r->permissions ?? [],
                'permissions_count' => $r->permissions_catalog_count,
            ]);

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'catalog' => \App\Support\Permissions::CATALOG,
        ]);
    }
}
