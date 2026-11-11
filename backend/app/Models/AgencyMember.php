<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        $rolePerms = $this->role ? ($this->role->permissions ?? []) : [];
        $extras = $this->permissions ?? [];
        return array_values(array_unique(array_merge($rolePerms, $extras)));
    }
}
