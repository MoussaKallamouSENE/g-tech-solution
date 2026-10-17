<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendOrderNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 21_600;

    public function __construct(
        public int $orderId,
        public string $channel,
    ) {}

    public function uniqueId(): string
    {
        return $this->orderId.':'.$this->channel;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(OrderNotificationService $service): void
    {
        $service->send($this->orderId, $this->channel);
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            app(OrderNotificationService::class)->markFailure($order, $this->channel, $exception);
        }
    }
}
