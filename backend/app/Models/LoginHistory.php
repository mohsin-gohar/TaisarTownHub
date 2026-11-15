<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only trail of sign-in attempts (Phase 6 — login history and
 * suspicious-login monitoring). Rows are never updated except to mark a
 * suspicious event, and never deleted by the application.
 */
class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'success',
        'mfa_used',
        'suspicious',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'mfa_used' => 'boolean',
            'suspicious' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
