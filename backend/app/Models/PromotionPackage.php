<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-configurable featured-placement package.
 *
 * Prices and durations live in the database (never hardcoded in the UI), so
 * the platform can launch with complimentary packages and later switch to paid
 * placement without a deploy.
 */
class PromotionPackage extends Model
{
    protected $table = 'promotion_packages';

    protected $fillable = [
        'key', 'name', 'description', 'price', 'currency', 'duration_days',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(AgencyPromotion::class, 'package_id');
    }
}
