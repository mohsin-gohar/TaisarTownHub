<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentShare extends Model
{
    protected $table = 'document_shares';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'document_id', 'agency_id', 'token_hash', 'expires_at',
        'max_downloads', 'download_count', 'last_downloaded_at',
        'revoked_at', 'note', 'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function issue(Document $document, string $agencyId, ?int $userId, array $attrs): array
    {
        $plain = Str::random(48);
        $share = self::create(array_merge([
            'id' => 'shr_' . bin2hex(random_bytes(8)),
            'document_id' => $document->id,
            'agency_id' => $agencyId,
            'token_hash' => hash('sha256', $plain),
            'created_by' => $userId,
        ], $attrs));

        return [$share, $plain];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && ($this->max_downloads === null || $this->download_count < $this->max_downloads);
    }
}
