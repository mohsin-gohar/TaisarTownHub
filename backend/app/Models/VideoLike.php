<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One like per user per video (unique index enforces idempotency). */
class VideoLike extends Model
{
    protected $fillable = [
        'agency_video_id',
        'user_id',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(AgencyVideo::class, 'agency_video_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
