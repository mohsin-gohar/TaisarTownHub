<?php

namespace App\Mail;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Email alert for a saved marketplace search.
 *
 * Sent synchronously by the saved-searches:send-alerts command so delivery
 * does not depend on a queue worker being healthy. The command itself is
 * scheduled and idempotent per run window.
 */
class SavedSearchMatchesMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\Property> $matches
     */
    public function __construct(
        public SavedSearch $search,
        public Collection $matches
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New matches for "' . $this->search->name . '" | TaisarTownHub'
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $items = $this->matches->map(fn ($property) => [
            'id' => $property->id,
            'title' => $property->title,
            'price' => $property->formattedPrice ?: 'PKR ' . number_format((int) $property->pricePKR),
            'location' => $property->location,
            'type' => $property->type,
            'size' => $property->sizeSqYards,
            'url' => $frontend . '/properties?property=' . urlencode((string) $property->id),
        ])->values();

        return new Content(
            view: 'emails.saved-search-matches',
            text: 'emails.saved-search-matches-text',
            with: [
                'searchName' => $this->search->name,
                'summary' => $this->search->describeFilters(),
                'items' => $items,
                'frontendUrl' => $frontend,
                'manageUrl' => $frontend . '/properties',
            ],
        );
    }
}
