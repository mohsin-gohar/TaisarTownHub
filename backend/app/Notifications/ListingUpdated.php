<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListingUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $entityId = ''
    ) {
    }

    /**
     * Database channel payload — stored in the `notifications` table and
     * surfaced by GET /api/notifications.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'entity_id' => $this->entityId,
            'url' => $this->entityId ? '/admin/properties?property=' . $this->entityId : null,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
