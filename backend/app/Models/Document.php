<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private vault document. The physical file lives on the `documents`
 * private disk; `disk_path`/`stored_name`/`checksum` are hidden from all
 * API responses and are only used by the authorized download/preview path.
 */
class Document extends Model
{
    use SoftDeletes;

    protected $table = 'documents';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'agency_id', 'folder_id', 'property_id', 'category', 'title', 'original_name',
        'stored_name', 'disk_path', 'storage_disk', 'mime_type', 'size_bytes', 'checksum',
        'is_sensitive', 'version', 'status', 'scan_status', 'scan_threats', 'scan_completed_at',
        'scanner', 'tags', 'uploaded_by', 'deleted_by',
    ];

    protected $hidden = [
        'disk_path', 'stored_name', 'checksum', 'original_name',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'tags' => 'array',
            'size_bytes' => 'integer',
            'version' => 'integer',
            'scan_threats' => 'array',
            'scan_completed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class, 'document_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrashed($query)
    {
        return $query->where('status', 'trashed');
    }

    public function isPdf(): bool
    {
        return str_contains($this->mime_type, 'pdf');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
