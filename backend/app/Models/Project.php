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
        'id', 'title', 'description', 'location', 'status',
        'isFeatured', 'images', 'features'
    ];

    protected $casts = [
        'images' => 'array',
        'features' => 'array',
        'isFeatured' => 'boolean',
    ];
}