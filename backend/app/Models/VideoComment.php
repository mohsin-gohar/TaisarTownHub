<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoComment extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'agency_video_id', 'userName', 'comment', 'isDealer', 'user_id'
    ];

    // Internal account identifiers are used for server-side attribution only.
    // Public gallery responses should never expose them.
    protected $hidden = ['user_id'];

    protected $casts = [
        'isDealer' => 'boolean',
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
