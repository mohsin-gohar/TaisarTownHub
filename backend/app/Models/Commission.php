<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'agency_id', 'transaction_id', 'dealer_id', 'user_id', 'beneficiary_type',
        'amount', 'rate', 'status', 'accrued_at', 'paid_at', 'paid_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            // DECIMAL(14,2) must remain an exact decimal string.
            'rate' => 'float',
            'accrued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** Numeric JSON compatibility; persisted DECIMAL remains exact. */
    public function getAmountAttribute($value): float
    {
        return (float) $value;
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealer_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
