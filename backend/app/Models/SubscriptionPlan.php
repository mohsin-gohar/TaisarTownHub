<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'price', 'currency', 'interval',
        'limits', 'features', 'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AgencySubscription::class, 'plan_id');
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?: static::where('is_active', true)->orderBy('sort_order')->first();
    }
}
