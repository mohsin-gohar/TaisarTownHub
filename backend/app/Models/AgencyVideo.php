<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyVideo extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'videoUrl', 'thumbnailUrl',
        'agencyId', 'agencyName', 'dealerName', 'dealerAvatar',
        'phase', 'sector', 'duration', 'likes', 'views', 'tags'
    ];

    protected $casts = [
        'tags' => 'array',
        'likes' => 'integer',
        'views' => 'integer'
    ];

    public function comments()
    {
        return $this->hasMany(VideoComment::class, 'agency_video_id', 'id')->latest();
    }
}
