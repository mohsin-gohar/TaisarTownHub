<?php

namespace App\Notifications;

use App\Models\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email verification link for SPA-based verification. The plain token is
 * generated here, stored as a SHA-256 hash server-side, and delivered only
 * inside this mail link. Queued (G-9) so mail dispatch never blocks the
 * registration request.
 */
class VerifyEmailLink extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry transient mail/database transport failures without blocking requests. */
    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 120, 300];
    public string $plainToken;

    public function __construct(?string $plainToken = null)
    {
        $this->plainToken = $plainToken ?? bin2hex(random_bytes(24));
    }

    /** Create (or replace) the stored hash for this email. */
    public static function issueFor(string $email): self
    {
        $notification = new self();
        EmailVerificationToken::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'token_hash' => hash('sha256', $notification->plainToken),
                'created_at' => now(),
            ]
        );

        return $notification;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontend . '/verify-email?email=' . urlencode($notifiable->email)
            . '&token=' . urlencode($this->plainToken);

        return (new MailMessage)
            ->subject('Verify Your Email — TaisarTownHub')
            ->greeting('Assalam-o-Alaikum!')
            ->line('Confirm your email address to activate your TaisarTownHub account.')
            ->action('Verify Email', $url)
            ->line('This secure link expires in 60 minutes.')
            ->line('If you did not create this account, no action is required.');
    }
}
