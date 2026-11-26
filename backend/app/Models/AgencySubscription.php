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
        if (in_array($this->status, ['expired', 'cancelled', 'past_due'], true)) {
            return true;
        }

        if ($this->status === 'trialing') {
            return $this->trial_ends_at === null || $this->trial_ends_at->isPast();
        }

        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /** Effective limit for a key (agency override > plan limit). */
    public function limit(string $key, $default = null)
    {
        if (array_key_exists($key, $this->limits_override ?? [])) {
            return $this->limits_override[$key];
        }

        $planLimits = $this->plan?->limits ?? [];
        if (array_key_exists($key, $planLimits)) {
            return $planLimits[$key]; // explicit null means unlimited
        }

        return $default;
    }
}
