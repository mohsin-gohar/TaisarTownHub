<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'agency_id', 'inquiry_id', 'type', 'body', 'due_at',
        'actor_user_id', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'due_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'inquiry_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
