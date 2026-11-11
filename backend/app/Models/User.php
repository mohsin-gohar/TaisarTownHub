<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\Permissions;
use App\Support\RolePermissions;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'dealer_id',
        'phone',
        'status',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'failed_attempts',
        'locked_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Agency tenancy                                                      */
    /* ------------------------------------------------------------------ */

    public function memberships(): HasMany
    {
        return $this->hasMany(AgencyMember::class, 'user_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active')->with(['agency', 'role']);
    }

    /** Agencies this user may act for (active memberships only). */
    public function memberAgencies(): array
    {
        return $this->activeMemberships()
            ->get()
            ->map(fn ($m) => [
                'agency_id' => $m->agency_id,
                'agency_name' => $m->agency?->name,
                'role_id' => $m->role_id,
                'role_key' => $m->role?->key,
                'role_name' => $m->role?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve the acting agency for this request. NEVER trusts a client-supplied
     * agency id: the override header must match one of the user's own active
     * memberships. Platform users have no agency (they act platform-wide and
     * their cross-agency access is audited separately).
     */
    public function resolveAgencyId(?string $requestedAgencyId = null): ?string
    {
        $memberships = $this->activeMemberships()->get();

        if ($memberships->isEmpty()) {
            return null; // platform-level user
        }

        if ($requestedAgencyId !== null && $requestedAgencyId !== '') {
            $match = $memberships->firstWhere('agency_id', $requestedAgencyId);
            return $match ? $match->agency_id : null; // not a member → deny, do not fall back
        }

        return $memberships->first()->agency_id;
    }

    /* ------------------------------------------------------------------ */
    /* Permission resolution                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Effective permission keys.
     * - With an agency: the member's role grants (+ per-member extras).
     * - Without: legacy platform matrix ∪ seeded platform role row.
     *
     * @return string[]
     */
    public function permissionsFor(?string $agencyId = null): array
    {
        if ($agencyId !== null) {
            $membership = $this->memberships()
                ->where('agency_id', $agencyId)
                ->where('status', 'active')
                ->with('role')
                ->first();

            if ($membership) {
                $keys = $membership->permissionKeys();
                // Expose legacy aliases both prefixed (catalog form) and bare
                // (the form legacy controllers pass to requireAny()).
                foreach ($keys as $key) {
                    if (str_starts_with($key, 'legacy.')) {
                        $keys[] = substr($key, 7);
                    }
                }
                return array_values(array_unique($keys));
            }
            // Requested an agency the user does not belong to → no agency perms.
            return [];
        }

        $platform = RolePermissions::forRole($this->role);
        $roleRow = Role::where('scope', 'platform')->where('key', $this->role)->first();
        if ($roleRow) {
            $platform = array_values(array_unique(array_merge($platform, $roleRow->permissions ?? [])));
        }

        return $platform;
    }

    /**
     * Check a permission, optionally scoped to the acting agency.
     * Backward compatible: single-argument calls keep legacy platform behavior.
     */
    public function hasPermission(string $permission, ?string $agencyId = null): bool
    {
        if ($agencyId !== null) {
            return in_array($permission, $this->permissionsFor($agencyId), true);
        }

        // Platform resolution: legacy matrix first (fast path, keeps old tests true),
        // then enterprise platform role grants.
        if (RolePermissions::allows($this->role, $permission)) {
            return true;
        }

        return in_array($permission, $this->permissionsFor(null), true);
    }

    /** True when this user holds a platform-level role (not agency-scoped). */
    public function isPlatformUser(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'manager', 'moderator', 'support'], true)
            && $this->memberships()->where('status', 'active')->doesntExist();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Get the dealer associated with this user.
     */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'dealer_id', 'id');
    }
}