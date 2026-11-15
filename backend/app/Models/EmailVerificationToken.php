<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One hashed email-verification token per email address. Plain tokens are
 * never persisted (see App\Notifications\VerifyEmailLink).
 */
class EmailVerificationToken extends Model
{
    public $timestamps = false;

    /** The table's primary key is the email address (one live token per email). */
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['email', 'token_hash', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
