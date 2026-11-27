<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security notification sent to the account owner whenever a password is
 * changed (self-service reset or platform-admin reset). Queued so mail
 * dispatch never blocks the request that changed the password.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry transient mail/database transport failures without blocking requests. */
    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly ?string $ipAddress = null,
        public readonly bool $revokedSessions = true,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $ipLine = $this->ipAddress
            ? "The request was made from IP address {$this->ipAddress}."
            : 'The origin of the request could not be determined.';

        $message = (new MailMessage)
            ->subject('Your TaisarTownHub password was changed')
            ->greeting('Assalam-o-Alaikum ' . ($notifiable->name ?: 'there') . ',')
            ->line('The password for your TaisarTownHub account was just changed.')
            ->line($ipLine);

        if ($this->revokedSessions) {
            $message->line('All other sessions and API tokens were signed out as a precaution.');
        }

        return $message
            ->action('Sign in', $frontend . '/login')
            ->line('If this was not you, reset your password immediately and contact support.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Password changed',
            'message' => 'Your account password was changed. All other sessions were signed out.',
            'category' => 'security',
            'ip_address' => $this->ipAddress,
        ];
    }
}
