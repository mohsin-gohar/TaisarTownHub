<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'dealNumber', 'propertyId', 'propertyTitle', 'plotNumber',
        'sector', 'sellerName', 'buyerName', 'dealerId', 'dealerName',
        'transactionType', 'agreementAmountPKR', 'advancePaymentPKR',
        'remainingBalancePKR', 'commissionAmountPKR', 'commissionStatus',
        'dealStatus',
        // Enterprise fields
        'agency_id', 'created_by', 'updated_by',
        'voided_at', 'void_reason', 'voided_by',
        'approved_at', 'approved_by',
        'paymentMethod', 'paymentStatus', 'transactionDate', 'internalRemarks',
    ];

    protected $casts = [
        'voided_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    /**
     * Legacy mirrored record in the generic business_records table.
     * FinanceController eager-loads this on list/detail; the relation was
     * referenced but never defined, so any agency with at least one deal got
     * a 500 (empty-list tests could not catch it).
     */
    public function businessRecord(): HasOne
    {
        return $this->hasOne(BusinessRecord::class, 'record_id', 'id')
            ->where('collection', 'transactions');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'propertyId', 'id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class, 'dealerId', 'id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id')->orderBy('received_at');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'transaction_id')->orderBy('installment_no');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'transaction_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeForAgency($query, ?string $agencyId)
    {
        return $agencyId === null
            ? $query->whereNull('agency_id')
            : $query->where('agency_id', $agencyId);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Exact total money received in integer paise (soft-deleted rows excluded). */
    public function totalPaidCents(): int
    {
        $amount = (string) $this->payments()->sum('amount');
        if (! preg_match('/^(-?)(\\d+)(?:\\.(\\d{1,2}))?$/', $amount, $matches)) {
            return 0;
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');
        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    /** Numeric compatibility accessor for existing API/report consumers. */
    public function totalPaid(): float
    {
        return $this->totalPaidCents() / 100;
    }

    public function outstandingBalance(): float
    {
        $agreementCents = ((int) $this->agreementAmountPKR) * 100;
        return max(0, $agreementCents - $this->totalPaidCents()) / 100;
    }

    /**
     * Recalculate the payment status from exact paise totals. The legacy
     * transaction summary columns are BIGINT whole-PKR fields, so their
     * display values are rounded only when written to those summary columns.
     */
    public function recalculateStatus(): void
    {
        $totalPaidCents = $this->totalPaidCents();
        $agreementCents = ((int) $this->agreementAmountPKR) * 100;

        $this->advancePaymentPKR = (int) round($totalPaidCents / 100);
        $this->remainingBalancePKR = intdiv(max(0, $agreementCents - $totalPaidCents) + 50, 100);

        if ($totalPaidCents <= 0) {
            $this->paymentStatus = 'Pending';
        } elseif ($totalPaidCents >= $agreementCents && $agreementCents > 0) {
            $this->paymentStatus = 'Fully Paid';
        } else {
            $this->paymentStatus = 'Partially Paid';
        }

        $this->save();
    }
}
