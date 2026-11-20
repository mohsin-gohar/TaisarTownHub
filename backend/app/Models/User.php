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
        'google_id',
        'google_avatar',
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
        'mfa_secret',
        'mfa_recovery_codes',
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
            // MFA material is encrypted at rest (APP_KEY), never stored plain.
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
        ];
    }

    /** True when the account has a working second factor enrolled. */
    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && $this->mfa_secret !== null;
    }

    /**
     * Account states that must never obtain a usable authentication session.
     *
     * Pending accounts may sign in to see their approval status, but suspended,
     * inactive and rejected accounts are refused at the credential boundary
     * (password and Google OAuth alike) instead of only at the workspace gate.
     *
     * @return 'suspended'|'inactive'|'rejected'|null
     */
    public function authenticationBlockedReason(): ?string
    {
        return in_array($this->status, ['suspended', 'inactive', 'rejected'], true)
            ? $this->status
            : null;
    }

    /** Emails are stored lower-case so OAuth linking cannot create duplicates. */
    public function setEmailAttribute(mixed $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $value));
    }

    /** Append-only sign-in trail for this account. */
    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class, 'user_id')->orderByDesc('created_at');
    }

    /**
     * Password reset links point at the SPA frontend (no web route exists on
     * this token-only API).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordLink($token));
    }

    /** Convenience for verification flows. */
    public function markEmailVerified(): bool
    {
        if ($this->email_verified_at !== null) {
            return false;
        }
        $this->forceFill(['email_verified_at' => now()])->save();

        return true;
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
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => [
                'agency_id' => $m->agency_id,
                'agency_name' => $m->agency?->name,
                'agency_status' => $m->agency?->status,
                'verification_status' => $m->agency?->verification_status,
                'role_id' => $m->role_id,
                'role_key' => $m->role?->key,
                'role_name' => $m->role?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve context, not authorization. A null result is NOT proof of platform
     * access: workspaceAccess() must allow the request before it may proceed.
     */
    public function resolveAgencyId(?string $requestedAgencyId = null): ?string
    {
        if ($requestedAgencyId !== null && $requestedAgencyId !== '') {
            if ($this->hasPermission('admin.manage_agencies')) {
                return Agency::whereKey($requestedAgencyId)->exists() ? $requestedAgencyId : null;
            }

            return $this->activeMemberships()->where('agency_id', $requestedAgencyId)->value('agency_id');
        }

        if ($this->isPlatformUser()) {
            return null;
        }

        return $this->activeMemberships()->orderBy('id')->value('agency_id');
    }

    /**
     * Authoritative workspace eligibility, evaluated from current database rows
     * on every request (never token claims or cached membership relations).
     * An explicit agency must never silently fall back to a different tenant.
     *
     * @return array{allowed: bool, status: string, message: string}
     */
    public function workspaceAccess(?string $agencyId = null): array
    {
        $result = static fn (string $status, string $message): array => [
            'allowed' => $status === 'approved',
            'status' => $status,
            'message' => $message,
        ];

        if ($this->status !== 'active') {
            $status = in_array($this->status, ['pending', 'rejected', 'suspended'], true)
                ? $this->status : 'inactive';
            return $result($status, match ($status) {
                'pending' => 'Your account is pending platform-owner approval.',
                'rejected' => 'Your account has been rejected. Contact support.',
                'suspended' => 'Your account has been suspended. Contact support.',
                default => 'Your account is inactive. Contact support.',
            });
        }

        $agencyId = $agencyId === '' ? null : $agencyId;
        if ($this->isPlatformUser() && ($agencyId === null || $this->hasPermission('admin.manage_agencies'))) {
            if ($agencyId !== null && !Agency::whereKey($agencyId)->exists()) {
                return $result('agency_unavailable', 'The requested agency is unavailable.');
            }
            // Explicit platform administrators must be able to review even
            // pending/rejected/suspended agencies. Endpoint permissions still apply.
            return $result('approved', 'Workspace access is approved.');
        }

        $membership = $agencyId !== null
            ? $this->memberships()->with('agency')->where('agency_id', $agencyId)->first()
            : $this->activeMemberships()->orderBy('id')->first();

        if (!$membership) {
            if ($agencyId !== null) {
                return $result('forbidden', 'Access denied for the requested agency.');
            }
            if ($this->memberships()->exists()) {
                return $result('membership_inactive', 'Your agency membership is inactive. Contact your agency owner.');
            }
            return $result('membership_required', 'An approved agency membership is required to access the workspace.');
        }

        if ($membership->status !== 'active') {
            return $result('membership_inactive', 'Your agency membership is inactive. Contact your agency owner.');
        }

        $agency = $membership->agency;
        if (!$agency) {
            return $result('agency_unavailable', 'Your agency is unavailable. Contact support.');
        }
        if ($agency->status === 'suspended') {
            return $result('suspended', 'Your agency has been suspended. Contact support.');
        }
        if ($agency->status === 'rejected' || $agency->verification_status === 'rejected') {
            return $result('rejected', 'Your agency has been rejected. Contact support.');
        }
        if ($agency->status === 'active' && $agency->verification_status === 'verified') {
            return $result('approved', 'Workspace access is approved.');
        }
        if (in_array($agency->status, ['active', 'pending'], true)) {
            return $result('pending', 'Your agency is pending platform-owner approval.');
        }

        return $result('inactive', 'Your agency is inactive. Contact support.');
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

        return $this->isPlatformUser() ? $this->platformPermissions() : [];
    }

    /** Raw platform-role grants, never agency membership grants. */
    private function platformPermissions(): array
    {
        $platform = RolePermissions::forRole($this->role);
        $roleRow = Role::where('scope', 'platform')->where('key', $this->role)->first();
        if ($roleRow) {
            $platform = array_values(array_unique(array_merge($platform, $roleRow->permissions ?? [])));
        }

        return $platform;
    }

    /** Check a permission in the resolved agency or intentional platform scope. */
    public function hasPermission(string $permission, ?string $agencyId = null): bool
    {
        return in_array($permission, $this->permissionsFor($agencyId), true);
    }

    /** Inactive memberships must never promote a member into platform scope. */
    public function isPlatformUser(): bool
    {
        if (in_array('admin.manage_agencies', $this->platformPermissions(), true)) {
            return true;
        }

        return in_array($this->role, ['super_admin', 'admin', 'manager', 'moderator', 'support', 'operator'], true)
            && $this->memberships()->doesntExist();
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