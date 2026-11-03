<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'dealStatus'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'propertyId', 'id');
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'dealerId', 'id');
    }
}