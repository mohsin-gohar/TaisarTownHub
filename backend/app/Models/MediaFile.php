<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use SoftDeletes;

    protected $table = 'media_files';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'agency_id', 'property_id', 'folder', 'title', 'description', 'type',
        'original_name', 'stored_name', 'disk_path', 'url', 'mime_type',
        'size_bytes', 'thumbnail_url', 'dimensions', 'visibility', 'tags',
        'status', 'scan_status', 'scan_completed_at', 'sold_at', 'sold_price_pk', 'cost_price_pk', 'profit_pk',
        'buyer_name', 'buyer_phone', 'buyer_national_id_masked', 'sold_by',
        'uploaded_by',
    ];

    protected $hidden = ['disk_path', 'stored_name', 'original_name'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'size_bytes' => 'integer',
            'sold_price_pk' => 'float',
            'cost_price_pk' => 'float',
            'profit_pk' => 'float',
            'sold_at' => 'datetime',
            'scan_completed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
