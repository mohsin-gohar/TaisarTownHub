<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'document_id', 'agency_id', 'user_id', 'action', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

}
