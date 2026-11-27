<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionPayment extends Model
{
    use SoftDeletes;

    protected $table = 'transaction_payments';
    protected $fillable = [
        'agency_id', 'transaction_id', 'amount', 'type', 'method', 'reference',
        'receipt_no', 'received_at', 'recorded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            // DECIMAL(14,2) must remain an exact decimal string.
            'received_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
