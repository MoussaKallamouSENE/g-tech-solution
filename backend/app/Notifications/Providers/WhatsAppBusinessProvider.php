<?php

namespace App\Notifications\Providers;

use App\Notifications\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppBusinessProvider implements WhatsAppProvider
{
    public function __construct(
        protected array $config = []
    ) {
        $this->config = config('services.whatsapp', []);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && ! empty($this->config['token'] ?? null)
            && ! empty($this->config['phone_number_id'] ?? null)
            && ! empty($this->config['template_name'] ?? null);
    }

    public function sendTemplate(string $to, array $parameters): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! str_starts_with(trim($to), '+')) {
            throw new RuntimeException('Le numéro WhatsApp doit être au format international avec le préfixe +.');
        }

        $recipient = $this->normalizePhoneNumber($to);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $recipient)) {
            throw new RuntimeException('Le numéro WhatsApp ne respecte pas le format international.');
        }

        $response = Http::timeout(15)->withToken($this->config['token'])
            ->post(
                'https://graph.facebook.com/'.($this->config['api_version'] ?? 'v23.0').'/'.$this->config['phone_number_id'].'/messages',
                [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'template',
                    'template' => [
                        'name' => $this->config['template_name'],
                        'language' => [
                            'code' => $this->config['template_language'] ?? 'fr',
                        ],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(
                                fn (string $value): array => ['type' => 'text', 'text' => $value],
                                $parameters
                            ),
                        ]],
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException('Le fournisseur WhatsApp a retourné le statut HTTP '.$response->status().'.');
        }
        if (! is_string(data_get($response->json(), 'messages.0.id'))) {
            throw new RuntimeException('Le fournisseur WhatsApp n’a pas confirmé l’acceptation du message.');
        }

        return true;
    }

    protected function normalizePhoneNumber(string $phone): string
    {
        $value = preg_replace('/\D+/', '', $phone) ?? '';

        return ltrim($value, '0');
    }
}
