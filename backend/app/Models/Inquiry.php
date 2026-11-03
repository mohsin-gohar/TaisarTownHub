<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'fullName', 'email', 'phone', 'subject', 'message',
        'preferredPropertyOrProject', 'budgetRange', 'status',
        'adminNotes', 'source'
    ];

    protected $hidden = ['cnic']; // Hide CNIC from default serialization
}