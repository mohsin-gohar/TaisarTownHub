<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencySubscription extends Model
{
    protected $table = 'agency_subscriptions';
    protected $fillable = [
        'agency_id', 'plan_id', 'status', 'starts_at', 'ends_at',
        'trial_ends_at', 'auto_renew', 'cancelled_at', 'stripe_subscription_id',
        'provider', 'limits_override', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'limits_override' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'auto_renew' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /** Effective limit for a key (agency override > plan limit). */
    public function limit(string $key, $default = null)
    {
        if (array_key_exists($key, $this->limits_override ?? [])) {
            return $this->limits_override[$key];
        }
        return ($this->plan->limits[$key] ?? null) ?? $default;
    }
}
