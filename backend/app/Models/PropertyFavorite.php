<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'property_id' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'id');
    }
}
