<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Raised when a sign-in looks unusual (first time from an IP address or a new
 * device). Delivered in-app (Notifications centre) and by mail so the account
 * owner can react immediately.
 */
class SuspiciousLoginDetected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reason,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $detail = match ($this->reason) {
            'new_ip_address' => 'We noticed a sign-in from an IP address we have not seen before.',
            'new_device' => 'We noticed a sign-in from a device we have not seen before.',
            default => 'We noticed an unusual sign-in on your account.',
        };

        return (new MailMessage)
            ->subject('New sign-in to your TaisarTownHub account')
            ->greeting('Assalam-o-Alaikum ' . ($notifiable->name ?: 'there') . ',')
            ->line($detail)
            ->line('IP address: ' . ($this->ipAddress ?? 'unknown'))
            ->line('Device: ' . (($this->userAgent ? mb_substr($this->userAgent, 0, 180) : 'unknown')))
            ->action('Review your security settings', $frontend . '/login')
            ->line('If this was not you, change your password and contact support immediately.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'New sign-in detected',
            'message' => $this->reason === 'new_ip_address'
                ? 'A sign-in from a new IP address was recorded on your account.'
                : 'A sign-in from a new device was recorded on your account.',
            'category' => 'security',
            'ip_address' => $this->ipAddress,
            'reason' => $this->reason,
        ];
    }
}
