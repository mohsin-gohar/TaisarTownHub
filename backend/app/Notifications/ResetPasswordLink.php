<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset link notification pointing at the SPA frontend instead of a
 * web route (this API is token-only and has no web password-reset pages).
 * Queued (G-9) so SMTP latency never blocks the forgot-password request.
 */
class ResetPasswordLink extends ResetPasswordNotification implements ShouldQueue
{
    use Queueable;
    public function toMail($notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontend . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        $expire = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password — TaisarTownHub')
            ->greeting('Assalam-o-Alaikum!')
            ->line('A password reset was requested for your TaisarTownHub account.')
            ->action('Reset Password', $url)
            ->line("This secure link expires in {$expire} minutes.")
            ->line('If you did not request a password reset, no action is required.');
    }
}
