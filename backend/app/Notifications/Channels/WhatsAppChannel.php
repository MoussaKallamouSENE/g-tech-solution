<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\WhatsAppProvider;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(protected WhatsAppProvider $provider) {}

    public function send(object $notifiable, Notification $notification, ?string $recipientOverride = null): bool
    {
        if (! method_exists($notification, 'toWhatsAppTemplate')) {
            return false;
        }

        $recipient = $recipientOverride ?? $notifiable->phone ?? $notifiable->customer_phone ?? null;
        if (! is_string($recipient) || trim($recipient) === '') {
            return false;
        }

        if (! $this->provider->isEnabled()) {
            return false;
        }

        return $this->provider->sendTemplate($recipient, $notification->toWhatsAppTemplate());
    }
}
