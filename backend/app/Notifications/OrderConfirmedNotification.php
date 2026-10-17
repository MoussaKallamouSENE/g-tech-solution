<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toWhatsAppTemplate(): array
    {
        $items = $this->order->items->map(
            fn ($item): string => $item->product_name.' x '.$item->quantity
        )->implode(', ');

        return [
            $this->order->customer_name,
            $this->order->number,
            $items,
            number_format($this->order->total, 0, ',', ' ').' FCFA',
            $this->order->customer_address,
            'Confirmée',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $items = $this->order->items()->get();
        $lines = $items->map(function ($item) {
            return sprintf('%s × %s = %s', $item->product_name, $item->quantity, number_format($item->total, 0, ',', ' ').' FCFA');
        })->implode("\n");

        return (new MailMessage)
            ->subject('Confirmation de commande G-Tech Solution - '.$this->order->number)
            ->greeting('Bonjour '.$this->order->customer_name.',')
            ->line('Votre commande a bien été confirmée.')
            ->line('Statut : Confirmée')
            ->line('Numéro de commande : '.$this->order->number)
            ->line('Produit(s) :')
            ->line($lines)
            ->line('Montant total : '.number_format($this->order->total, 0, ',', ' ').' FCFA')
            ->line('Adresse de livraison : '.$this->order->customer_address)
            ->line('Téléphone : '.$this->order->customer_phone)
            ->line('Merci de votre confiance. Nous vous tiendrons informé de la suite.')
            ->salutation('Cordialement, l’équipe G-Tech Solution');
    }
}
