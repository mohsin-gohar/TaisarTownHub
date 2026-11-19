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

    public static function put(string $collection, string $recordId, array $payload, ?string $agencyId = null): self
    {
        $record = static::updateOrCreate(
            ['collection' => $collection, 'record_id' => $recordId],
            [
                'payload' => $payload,
                'deleted_at' => !empty($payload['deletedAt']) ? now() : null,
            ]
        );

        // Stamp ownership only when the row is still unowned. An existing
        // owner is never overwritten here; WorkspaceController::syncList is
        // the sole place allowed to keep/verify tenancy on writes.
        if ($agencyId !== null && $record->agency_id === null) {
            $record->agency_id = $agencyId;
            $record->save();
        }

        return $record;
    }
}
