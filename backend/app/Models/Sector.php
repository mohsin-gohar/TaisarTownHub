<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sector extends Model
{
    use SoftDeletes;
    protected $table = 'sectors';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'slug', 'phase', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'sector_id')->orderBy('sort_order');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'sector_id');
    }
}
