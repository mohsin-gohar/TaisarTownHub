<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One featured-agency placement.
 *
 * `active` rows may be scheduled in the future; `live` rows are the ones the
 * public marketplace may render right now. Payment state is tracked separately
 * from placement state so an unpaid or refunded placement can never be
 * presented as a paid advertisement.
 */
class AgencyPromotion extends Model
{
    protected $table = 'agency_promotions';

    public const STATUSES = ['pending', 'active', 'paused', 'expired', 'cancelled'];
    public const PAYMENT_STATUSES = ['waived', 'pending', 'paid', 'refunded'];

    protected $fillable = [
        'agency_id', 'package_id', 'status', 'position', 'starts_at', 'ends_at',
        'price', 'currency', 'payment_status', 'payment_method',
        'payment_reference', 'notes', 'created_by', 'approved_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PromotionPackage::class, 'package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Slots that hold/release capacity (scheduled or currently running). */
    public function scopeCurrent(Builder $query): Builder
    {
        // Future-dated active placements reserve a slot immediately. The
        // public/live scope below still hides them until starts_at is reached.
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    /** Rows that may be rendered publicly right now. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public function scopeExpiredDue(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /** Free launch: all placements are complimentary and labelled Featured. */
    public function publicBadge(): string
    {
        return 'Featured';
    }

    public function isPaid(): bool
    {
        return false;
    }
}
