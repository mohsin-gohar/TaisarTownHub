<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
        'stripe_session_id', 'stripe_payment_intent_id', 'stripe_subscription_id', 'stripe_invoice_id',
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
        $year = (int) now()->format('Y');

        $sequence = DB::transaction(function () use ($year): int {
            DB::table('subscription_invoice_number_sequences')->insertOrIgnore([
                'year' => $year,
                'next_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('subscription_invoice_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new \RuntimeException('Invoice number sequence row could not be locked.');
            }

            // Reconcile against imported/manual invoice rows as well as the
            // sequence counter so historical data can never cause reuse.
            $lastInvoiceNumber = self::where('invoice_number', 'like', "INV-{$year}-%")
                ->orderByDesc('invoice_number')
                ->value('invoice_number');
            $lastIssued = 0;
            if (is_string($lastInvoiceNumber)
                && preg_match('/^INV-\\d{4}-(\\d+)$/', $lastInvoiceNumber, $matches) === 1) {
                $lastIssued = (int) $matches[1];
            }

            $next = max((int) $row->next_number, $lastIssued) + 1;
            DB::table('subscription_invoice_number_sequences')
                ->where('year', $year)
                ->update(['next_number' => $next, 'updated_at' => now()]);

            return $next;
        }, 10);

        return sprintf('INV-%04d-%06d', $year, $sequence);
    }
}
