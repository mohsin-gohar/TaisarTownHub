<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Map extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'slug', 'sector', 'phase', 'category', 'description',
        'image_path', 'image_disk', 'image_mime', 'image_size_bytes',
        'image_width', 'image_height', 'image_checksum', 'display_order',
        'is_active', 'is_featured', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'display_order' => 'integer',
        'image_size_bytes' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected $hidden = ['image_disk'];

    public const CATEGORIES = [
        'overall_map' => 'Overall Map',
        'sector_map' => 'Sector Map',
        'phase_map' => 'Phase Map',
        'guide_map' => 'Guide Map',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $map) {
            if (empty($map->id)) {
                $map->id = 'map_' . bin2hex(random_bytes(8));
            }
            if (empty($map->slug)) {
                $base = Str::slug($map->title) ?: 'map';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $map->slug = $slug;
            }
        });
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('title');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getImageUrlAttribute(): string
    {
        return Storage::disk($this->image_disk ?: 'public')->url($this->image_path);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sector' => $this->sector,
            'phase' => $this->phase,
            'category' => $this->category,
            'category_label' => self::CATEGORIES[$this->category] ?? $this->category,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'image_width' => $this->image_width,
            'image_height' => $this->image_height,
            'is_featured' => $this->is_featured,
            'display_order' => $this->display_order,
        ];
    }
}
