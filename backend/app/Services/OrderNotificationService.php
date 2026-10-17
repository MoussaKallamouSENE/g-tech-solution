<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Contracts\WhatsAppProvider;
use App\Notifications\OrderConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class OrderNotificationService
{
    public function send(int $orderId, string $channel): void
    {
        if (! in_array($channel, ['email', 'whatsapp'], true)) {
            throw new RuntimeException('Canal de notification invalide.');
        }

        $order = Order::with(['items', 'user'])->findOrFail($orderId);
        if ($order->status !== 'confirmed') {
            return;
        }

        if ($channel === 'whatsapp' && ! $order->whatsapp_consent) {
            $this->markSkipped($order);

            return;
        }

        if ($channel === 'whatsapp' && ! app(WhatsAppProvider::class)->isEnabled()) {
            $order->updateQuietly(['whatsapp_status' => 'not_configured']);

            return;
        }

        $statusColumn = $channel.'_status';
        $attemptColumn = $channel.'_attempts';
        $errorColumn = $channel.'_error';
        $sentAtColumn = $channel.'_sent_at';

        $claimed = DB::table('orders')
            ->where('id', $orderId)
            ->whereIn($statusColumn, ['pending', 'failed'])
            ->update([
                $statusColumn => 'sending',
                $attemptColumn => DB::raw($attemptColumn.' + 1'),
                $errorColumn => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        try {
            if ($channel === 'email') {
                if (! $order->user || empty($order->user->email)) {
                    throw new RuntimeException('Le client ne possède pas d’adresse e-mail.');
                }

                Notification::sendNow($order->user, new OrderConfirmedNotification($order), ['mail']);
            } else {
                $sent = (new WhatsAppChannel(app(WhatsAppProvider::class)))
                    ->send($order->user ?? $order, new OrderConfirmedNotification($order), $order->customer_phone);

                if (! $sent) {
                    throw new RuntimeException('Le fournisseur WhatsApp a refusé le message.');
                }
            }

            $order->updateQuietly([
                $statusColumn => 'sent',
                $sentAtColumn => now(),
                $errorColumn => null,
            ]);
        } catch (Throwable $exception) {
            $this->markFailure($order, $channel, $exception);

            throw new RuntimeException($channel === 'email'
                ? 'Order email notification attempt failed.'
                : 'Order WhatsApp notification attempt failed.');
        }
    }

    public function markDispatchFailed(Order $order, string $channel): void
    {
        $order->updateQuietly([
            $channel.'_status' => 'failed',
            $channel.'_error' => 'La notification n’a pas pu être ajoutée à la file.',
        ]);
    }

    public function markFailure(Order $order, string $channel, Throwable $exception): void
    {
        DB::table('orders')
            ->where('id', $order->id)
            ->where($channel.'_status', '!=', 'sent')
            ->update([
                $channel.'_status' => 'failed',
                $channel.'_sent_at' => null,
                $channel.'_error' => $this->safeError($channel, $exception),
                'updated_at' => now(),
            ]);

        Log::warning('Order notification delivery failed', [
            'order_id' => $order->id,
            'channel' => $channel,
            'exception' => $exception::class,
        ]);
    }

    private function markSkipped(Order $order): void
    {
        $order->updateQuietly([
            'whatsapp_status' => 'skipped',
            'whatsapp_sent_at' => null,
            'whatsapp_error' => null,
        ]);
    }

    private function safeError(string $channel, Throwable $exception): string
    {
        if ($channel === 'whatsapp' && $exception instanceof RuntimeException
            && preg_match('/^Le fournisseur WhatsApp a retourné le statut HTTP [0-9]{3}\.$/', $exception->getMessage())) {
            return $exception->getMessage();
        }

        return $channel === 'email'
            ? 'Échec du transport e-mail ('.$exception::class.'); vérifier la configuration SMTP et les journaux backend.'
            : 'Échec WhatsApp ('.$exception::class.'); vérifier le modèle approuvé, le numéro et les journaux backend.';
    }
}
