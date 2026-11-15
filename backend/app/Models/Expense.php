<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Agency overhead expense (office, marketing, salaries…). Soft-deleted rows
 * remain for audit; money is integer PKR.
 */
class Expense extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['office', 'marketing', 'salaries', 'utilities', 'transport', 'legal', 'other'];
    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'cheque', 'card', 'online', 'other'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'agency_id', 'expense_date', 'category', 'amount_pkr',
        'description', 'payment_method', 'reference', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount_pkr' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
