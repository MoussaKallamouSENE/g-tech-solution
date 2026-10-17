<?php

namespace App\Notifications\Contracts;

interface WhatsAppProvider
{
    public function isEnabled(): bool;

    public function sendTemplate(string $to, array $parameters): bool;
}
