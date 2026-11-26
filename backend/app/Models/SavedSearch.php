<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A per-account marketplace search with optional email alerts.
 *
 * `filters` is an allow-listed, normalised array (see SavedSearchController)
 * so a client can never stash arbitrary payloads in the JSON column.
 */
class SavedSearch extends Model
{
    public const FREQUENCIES = ['instant', 'daily', 'weekly'];

    protected static function booted(): void
    {
        static::creating(function (self $search): void {
            if (blank($search->id)) {
                $search->id = 'ss_' . Str::lower(Str::random(24));
            }
        });
    }

    protected $table = 'saved_searches';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'name', 'filters', 'frequency', 'alerts_enabled',
        'last_run_at', 'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'alerts_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human-readable filter summary used in alert emails. */
    public function describeFilters(): string
    {
        $f = $this->filters ?? [];
        $parts = [];

        if (!empty($f['search'])) {
            $parts[] = '"' . $f['search'] . '"';
        }
        if (!empty($f['type']) && $f['type'] !== 'All') {
            $parts[] = $f['type'];
        }
        if (!empty($f['phase']) && $f['phase'] !== 'All') {
            $parts[] = $f['phase'];
        }
        if (!empty($f['sector']) && $f['sector'] !== 'All') {
            $parts[] = $f['sector'];
        }
        if (!empty($f['block'])) {
            $parts[] = 'Block ' . $f['block'];
        }
        if (!empty($f['sizeSqYards'])) {
            $parts[] = (int) $f['sizeSqYards'] . ' Sq. Yd.';
        }
        if (!empty($f['minPrice']) || !empty($f['maxPrice'])) {
            $min = !empty($f['minPrice']) ? 'PKR ' . number_format((int) $f['minPrice']) : 'any price';
            $max = !empty($f['maxPrice']) ? 'PKR ' . number_format((int) $f['maxPrice']) : 'any price';
            $parts[] = $min . ' – ' . $max;
        }
        if (!empty($f['bedrooms'])) {
            $parts[] = (int) $f['bedrooms'] . '+ beds';
        }
        if (!empty($f['possessionOnly'])) {
            $parts[] = 'possession ready';
        }
        if (!empty($f['listingPurpose'])) {
            $parts[] = $f['listingPurpose'] === 'rent' ? 'for rent' : 'for sale';
        }

        return $parts === [] ? 'All published listings' : implode(' · ', $parts);
    }
}
