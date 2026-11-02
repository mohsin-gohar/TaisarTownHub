<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'fullName', 'cnic', 'cnicMasked', 'phone', 'email',
        'agencyAffiliation', 'agencyOrBranch', 'role', 'commissionType',
        'commissionRate', 'totalEarnedPKR', 'pendingCommissionPKR',
        'assignedPropertiesCount', 'completedDealsCount', 'dealsClosed',
        'status', 'avatar'
    ];
}
