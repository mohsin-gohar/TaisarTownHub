<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentQuarantine extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'agency_id', 'document_id', 'original_name', 'stored_name',
        'disk_path', 'mime_type', 'size_bytes', 'checksum', 'reason',
        'threats', 'uploaded_by', 'created_at',
    ];

    protected $hidden = ['disk_path', 'stored_name', 'checksum'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'threats' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
