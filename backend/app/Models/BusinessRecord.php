<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRecord extends Model
{
    protected $fillable = [
        'collection',
        'record_id',
        'payload',
        'deleted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'deleted_at' => 'datetime',
    ];

    public static function put(string $collection, string $recordId, array $payload): self
    {
        return static::updateOrCreate(
            ['collection' => $collection, 'record_id' => $recordId],
            [
                'payload' => $payload,
                'deleted_at' => !empty($payload['deletedAt']) ? now() : null,
            ]
        );
    }
}
