<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rejected upload kept in the isolated quarantine disk for administrator
 * visibility (infected or scanned-while-unavailable). The bytes are never
 * web-served and never downloadable; `vault:purge-quarantine` erases them
 * after the retention window.
 */
class DocumentQuarantine extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'agency_id', 'document_id', 'original_name', 'stored_name',
        'disk_path', 'mime_type', 'size_bytes', 'checksum', 'reason',
        'is_sensitive', 'threats', 'uploaded_by', 'created_at',
    ];

    protected $hidden = ['disk_path', 'stored_name', 'checksum'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_sensitive' => 'boolean',
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
        return $this->belongsTo(Document::class, 'document_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
