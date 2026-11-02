<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'dealNumber', 'propertyId', 'propertyTitle', 'plotNumber',
        'sector', 'sellerName', 'buyerName', 'dealerId', 'dealerName',
        'transactionType', 'agreementAmountPKR', 'advancePaymentPKR',
        'remainingBalancePKR', 'commissionAmountPKR', 'commissionStatus',
        'dealStatus'
    ];
}
