<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * `cnic` holds the full national ID and is encrypted at rest (AES-256 via
     * Laravel's `encrypted` cast). It is hidden from every serialization
     * (`$hidden` below) — API responses only ever expose `cnicMasked`.
     */
    protected $casts = [
        'cnic' => 'encrypted',
    ];

    protected $fillable = [
        'id', 'fullName', 'cnic', 'cnicMasked', 'phone', 'email',
        'agencyAffiliation', 'agencyOrBranch', 'role', 'commissionType',
        'commissionRate', 'totalEarnedPKR', 'pendingCommissionPKR',
        'assignedPropertiesCount', 'completedDealsCount', 'dealsClosed',
        'status', 'avatar'
    ];

    protected $hidden = ['cnic'];

    public function user()
    {
        return $this->hasOne(User::class, 'dealer_id', 'id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'assignedDealerId', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'dealerId', 'id');
    }

    public function dealerFiles()
    {
        return $this->hasMany(DealerFile::class, 'dealer_id', 'id');
    }
}