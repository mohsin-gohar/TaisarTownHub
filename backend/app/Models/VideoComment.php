<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoComment extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'agency_video_id', 'userName', 'comment', 'isDealer'
    ];

    protected $casts = [
        'isDealer' => 'boolean'
    ];

    public function video()
    {
        return $this->belongsTo(AgencyVideo::class, 'agency_video_id', 'id');
    }
}
