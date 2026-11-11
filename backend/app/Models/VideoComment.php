<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoComment extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'agency_video_id', 'userName', 'comment', 'isDealer', 'user_id'
    ];

    protected $casts = [
        'isDealer' => 'boolean',
    ];

    public function video()
    {
        return $this->belongsTo(AgencyVideo::class, 'agency_video_id', 'id');
    }
}