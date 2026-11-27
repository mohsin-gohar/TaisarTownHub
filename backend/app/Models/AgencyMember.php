<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Permissions;

class AgencyMember extends Model
{
    protected $table = 'agency_members';

    protected $fillable = [
        'agency_id', 'user_id', 'role_id', 'status', 'permissions',
        'invited_by', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'deactivated_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Effective permission keys for this membership (role grants + per-member extras). */
    public function permissionKeys(): array
    {
        $role = $this->role;
        $roleBelongsToThisAgency = $role !== null
            && $role->scope === 'agency'
            && ($role->agency_id === null || (string) $role->agency_id === (string) $this->agency_id);

        // A corrupt/stale membership must never inherit platform grants or a
        // custom role owned by a different agency through its role_id.
        $rolePerms = $roleBelongsToThisAgency ? ($role->permissions ?? []) : [];
        // Preserve only catalogued agency permissions and their explicitly
        // mapped legacy aliases; unknown/platform keys are never effective.
        $allowedRoleKeys = Permissions::agencyAssignable();
        $allowedLegacyKeys = [];
        foreach ($allowedRoleKeys as $key) {
            foreach (Permissions::LEGACY_ALIASES[$key] ?? [] as $alias) {
                $allowedLegacyKeys[] = 'legacy.' . $alias;
            }
        }
        $rolePerms = array_values(array_intersect(
            $rolePerms,
            array_merge($allowedRoleKeys, $allowedLegacyKeys)
        ));
        $extras = array_values(array_intersect($this->permissions ?? [], $allowedRoleKeys));

        return array_values(array_unique(array_merge($rolePerms, $extras)));
    }
}
