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

    /**
     * Unique sequential invoice number: INV-YYYY-NNNNNN.
     * The yearly sequence row is locked and incremented transactionally so
     * simultaneous invoice creation cannot reserve the same number.
     */
    public static function nextNumber(): string
    {
        return DB::transaction(function (): string {
            $year = (int) now()->format('Y');
            $sequence = DB::table('invoice_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                // Initialize from legacy invoices on first use after rollout.
                $latest = self::where('invoice_number', 'like', "INV-{$year}-%")
                    ->orderByDesc('invoice_number')
                    ->value('invoice_number');
                $next = 1;
                if (is_string($latest) && preg_match('/^INV-\\d{4}-(\\d+)$/', $latest, $matches)) {
                    $next = (int) $matches[1] + 1;
                }

                DB::table('invoice_sequences')->insertOrIgnore([
                    'year' => $year,
                    'next_number' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = DB::table('invoice_sequences')
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$sequence) {
                throw new \\RuntimeException('Unable to reserve an invoice number.');
            }

            $number = (int) $sequence->next_number;
            DB::table('invoice_sequences')->where('year', $year)->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

            return sprintf('INV-%s-%06d', $year, $number);
        });
    }
}
