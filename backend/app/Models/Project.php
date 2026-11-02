<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'subtitle', 'description', 'phase', 'sector',
        'startingPricePKR', 'formattedPrice', 'status', 'images', 'features'
    ];

    protected $casts = [
        'images' => 'array',
        'features' => 'array'
    ];
}
