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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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

        // Update advancePaymentPKR to reflect total collected
        $this->advancePaymentPKR = $totalPaid;

        // Calculate remaining balance
        $this->remainingBalancePKR = max(0, $agreementAmount - $totalPaid);

        // Determine payment status
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
