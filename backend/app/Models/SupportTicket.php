<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform support ticket raised by an (usually agency) account.
 * Content lives in immutable support_messages rows.
 */
class SupportTicket extends Model
{
    public const CATEGORIES = ['general', 'billing', 'technical', 'document', 'finance'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const STATUSES = ['open', 'pending', 'resolved', 'closed'];

    protected $table = 'support_tickets';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'agency_id', 'user_id', 'subject', 'category', 'priority',
        'status', 'assigned_to', 'first_response_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at');
    }
}
