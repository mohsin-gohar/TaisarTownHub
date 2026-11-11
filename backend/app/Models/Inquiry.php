<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'fullName', 'email', 'phone', 'subject', 'message',
        'preferredPropertyOrProject', 'budgetRange', 'status',
        'adminNotes', 'source',
        // Enterprise CRM fields
        'agency_id', 'property_id', 'assigned_agent_id', 'lead_status',
        'follow_up_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'inquiry_id')->orderByDesc('created_at');
    }

    public function scopeForAgency($query, ?string $agencyId)
    {
        return $agencyId === null
            ? $query->whereNull('agency_id')
            : $query->where('agency_id', $agencyId);
    }
}
