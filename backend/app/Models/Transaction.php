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
     * FinanceController eager-loads this on list/detail.
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Child ledger rows must agree with the parent transaction's tenant.
     *
     * The individual foreign keys only prove that both IDs exist; they do
     * not prove that the payment belongs to the same agency as this deal.
     * The correlated ownership check protects normal and eager-loaded
     * relationships, including platform-wide lists spanning many agencies.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id')
            ->whereHas('transaction', fn ($query) => $query->whereColumn(
                'transactions.agency_id',
                'transaction_payments.agency_id'
            ));
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'transaction_id')
            ->whereHas('transaction', fn ($query) => $query->whereColumn(
                'transactions.agency_id',
                'payment_schedules.agency_id'
            ));
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'transaction_id')
            ->whereHas('transaction', fn ($query) => $query->whereColumn(
                'transactions.agency_id',
                'commissions.agency_id'
            ));
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

    /** Total money received against this deal. */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->agreementAmountPKR - $this->totalPaid());
    }

    /**
     * Recalculate the payment status based on total payments received.
     * Updates advancePaymentPKR and remainingBalancePKR to reflect current totals.
     * Updates paymentStatus to reflect payment progress.
     */
    public function recalculateStatus(): void
    {
        $totalPaid = $this->totalPaid();
        $agreementAmount = (float) $this->agreementAmountPKR;

        $this->advancePaymentPKR = $totalPaid;
        $this->remainingBalancePKR = max(0, $agreementAmount - $totalPaid);

        if ($totalPaid <= 0) {
            $this->paymentStatus = 'Pending';
        } elseif ($totalPaid >= $agreementAmount && $agreementAmount > 0) {
            $this->paymentStatus = 'Fully Paid';
        } else {
            $this->paymentStatus = 'Partially Paid';
        }

        $this->save();
    }
}
