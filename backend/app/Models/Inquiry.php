<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'fullName', 'email', 'phone', 'subject', 'message',
        'preferredPropertyOrProject', 'budgetRange', 'status',
        'adminNotes', 'source'
    ];
}
