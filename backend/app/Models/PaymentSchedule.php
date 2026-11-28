<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSchedule extends Model
{
    protected $table = 'payment_schedules';
    protected $fillable = [
        'agency_id', 'transaction_id', 'installment_no', 'due_date', 'amount',
        'status', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
