<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor',
        'role',
        'action',
        'target',
        'detail',
        'ip',
        'created_at',
        // Enterprise fields
        'agency_id',
        'user_id',
        'entity_type',
        'entity_id',
        'metadata',
        'cross_agency',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cross_agency' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function scopeForAgency($query, ?string $agencyId)
    {
        return $agencyId === null
            ? $query->whereNull('agency_id')
            : $query->where('agency_id', $agencyId);
    }
}
