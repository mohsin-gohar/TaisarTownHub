<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'document_id', 'version', 'stored_name', 'disk_path', 'storage_disk', 'mime_type',
        'size_bytes', 'checksum', 'uploaded_by', 'note', 'created_at',
    ];

    protected $hidden = ['disk_path', 'stored_name', 'checksum'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
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
