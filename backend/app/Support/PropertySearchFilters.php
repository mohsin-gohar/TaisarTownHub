<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Single implementation of the public marketplace filter contract.
 *
 * Used by saved-search alerts (and available to any future server-side
 * search endpoint). Keeping the criteria here means the on-site listing
 * filters, the saved-search preview and the email alerts cannot silently
 * diverge.
 *
 * SECURITY: this builder is only ever applied on top of
 * `Property::query()->publiclyVisible()`; drafts, private rows and expired
 * listings can never appear in an alert.
 */
final class PropertySearchFilters
{
    /** Keys accepted from a saved-search payload. */
    public const KEYS = [
        'search', 'type', 'phase', 'sector', 'block', 'minPrice', 'maxPrice',
        'sizeSqYards', 'possessionOnly', 'listingPurpose', 'bedrooms', 'featured',
    ];

    public static function apply(Builder $query, array $filters): Builder
    {
        if (!empty($filters['search'])) {
            $s = self::escapeLike((string) $filters['search']);
            $query->where(function (Builder $q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('plotNumber', 'like', "%{$s}%")
                    ->orWhere('fileNumber', 'like', "%{$s}%")
                    ->orWhere('sector', 'like', "%{$s}%")
                    ->orWhere('location', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['type']) && $filters['type'] !== 'All') {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['phase']) && $filters['phase'] !== 'All') {
            $phase = (string) $filters['phase'];
            $digits = preg_replace('/\D+/', '', $phase);
            $query->where(function (Builder $q) use ($phase, $digits) {
                $q->where('phase', $phase);
                if ($digits !== '' && $digits !== $phase) {
                    // Legacy rows store "1"/"2"/"3" while the marketplace UI
                    // uses "Phase 1"; both spellings must match the alert.
                    $q->orWhere('phase', $digits);
                }
            });
        }

        if (!empty($filters['sector']) && $filters['sector'] !== 'All') {
            $sector = self::escapeLike((string) $filters['sector']);
            $query->where('sector', 'like', "%{$sector}%");
        }

        if (!empty($filters['block'])) {
            $block = self::escapeLike((string) $filters['block']);
            $query->where('block', 'like', "%{$block}%");
        }

        if (isset($filters['minPrice']) && (int) $filters['minPrice'] > 0) {
            $query->where('pricePKR', '>=', (int) $filters['minPrice']);
        }

        if (isset($filters['maxPrice']) && (int) $filters['maxPrice'] > 0) {
            $query->where('pricePKR', '<=', (int) $filters['maxPrice']);
        }

        if (isset($filters['sizeSqYards']) && (int) $filters['sizeSqYards'] > 0) {
            $query->where('sizeSqYards', (int) $filters['sizeSqYards']);
        }

        if (!empty($filters['bedrooms'])) {
            $query->where('bedrooms', '>=', (int) $filters['bedrooms']);
        }

        if (!empty($filters['possessionOnly'])) {
            $query->where('possessionReady', true);
        }

        // The public marketplace is sale-only. Legacy/internal rental rows must
        // never surface through saved searches or alert emails, regardless of
        // what a stored filter payload contains.
        $query->where(function ($q) use ($filters) {
            $purpose = $filters['listingPurpose'] ?? 'sale';
            $q->where('listing_purpose', $purpose === 'rent' ? 'sale' : (string) $purpose);
        });

        if (!empty($filters['featured'])) {
            $query->featured();
        }

        return $query;
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
