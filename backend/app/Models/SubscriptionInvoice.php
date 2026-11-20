<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A billing invoice for one agency/plan period. PAID invoices are immutable
 * financial history — only pending → paid (verify) and pending → void
 * transitions exist, both audited server-side.
 */
class SubscriptionInvoice extends Model
{
    public const STATUSES = ['pending', 'paid', 'failed', 'void'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'invoice_number', 'agency_id', 'plan_id', 'amount_pkr', 'tax_pkr',
        'total_pkr', 'currency', 'period_start', 'period_end', 'status',
        'paid_at', 'payment_method', 'payment_reference', 'verified_by', 'notes',
        'stripe_session_id', 'stripe_payment_intent_id', 'stripe_subscription_id',
        'flutterwave_tx_ref', 'flutterwave_tx_id', 'due_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_pkr' => 'integer',
            'tax_pkr' => 'integer',
            'total_pkr' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
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

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** Unique sequential invoice number: INV-YYYY-NNNNNN. */
    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $seq = (int) self::where('invoice_number', 'like', "INV-{$year}-%")->count() + 1;

        // Walk forward until unique (defensive against concurrent seeds).
        do {
            $number = sprintf('INV-%s-%06d', $year, $seq);
            $seq++;
        } while (self::where('invoice_number', $number)->exists());

        return $number;
    }
}
