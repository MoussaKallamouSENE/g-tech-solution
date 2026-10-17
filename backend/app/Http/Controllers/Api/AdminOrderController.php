<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderNotification;
use App\Models\MaintenanceRequest;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\Contracts\WhatsAppProvider;
use App\Services\InventoryMovementService;
use App\Services\OrderNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminOrderController extends Controller
{
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

    public const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly OrderNotificationService $orderNotificationService,
        private readonly InventoryMovementService $inventoryMovementService,
    ) {}

    public function stats()
    {
        $orders = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['total', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->toDateString());

        $salesByDay = collect(range(6, 0))->map(function ($i) use ($orders) {
            $date = now()->subDays($i)->toDateString();
            $day = $orders->get($date, collect());

            return ['date' => $date, 'total' => (int) $day->sum('total'), 'count' => $day->count()];
        })->values();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get([DB::raw('order_items.product_name as name'), DB::raw('SUM(order_items.quantity) as sold')]);

        return $this->basicStats() + [
            'sales_by_day' => $salesByDay,
            'top_products' => $topProducts,
            'repairs_new' => MaintenanceRequest::where('status', 'new')->count(),
            'recent_orders' => Order::latest()->orderByDesc('id')->limit(5)
                ->get(['id', 'number', 'customer_name', 'total', 'status', 'created_at']),
            'low_stock_products' => Product::where('is_active', true)
                ->whereColumn('stock', '<=', 'stock_alert_threshold')
                ->orderBy('stock')->limit(5)
                ->get(['id', 'name', 'stock', 'stock_alert_threshold']),
        ];
    }

    private function basicStats()
    {
        return [
            'orders_total' => Order::count(),
            'orders_pending' => Order::where('status', 'pending')->count(),
            'revenue' => (int) Order::where('status', 'delivered')->sum('total'),
            'low_stock' => Product::where('is_active', true)
                ->whereColumn('stock', '<=', 'stock_alert_threshold')
                ->count(),
        ];
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], [
            'status.in' => 'Statut de commande invalide.',
            'search.string' => 'La recherche doit être un texte.',
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'page.integer' => 'Le numéro de page doit être un entier.',
            'page.min' => 'Le numéro de page doit être supérieur à zéro.',
        ]);

        $query = Order::withCount('items');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->orderByDesc('id')->paginate(20)->withQueryString();
    }

    public function show(int $id)
    {
        return Order::with(['items', 'zone', 'user:id,name,email,phone'])->findOrFail($id);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Statut invalide.',
        ]);

        [$order, $newlyConfirmed] = DB::transaction(function () use ($id, $data, $request) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);

            if ($order->status === $data['status']) {
                return [$order->fresh(['items', 'zone', 'user']), false];
            }

            if (in_array($order->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Cette commande est terminée : son statut ne peut plus changer.'],
                ]);
            }

            if (! in_array($data['status'], self::STATUS_TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Transition impossible : une commande « {$order->status} » ne peut pas passer au statut « {$data['status']} »."],
                ]);
            }

            if ($data['status'] === 'confirmed' && $order->status === 'pending') {
                $productIds = $order->items->pluck('product_id')->filter()->all();
                $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

                foreach ($order->items as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = $products->get($item->product_id);
                    if (! $product) {
                        throw ValidationException::withMessages([
                            'status' => ['Un produit de cette commande n’est plus disponible.'],
                        ]);
                    }

                    if ($product->stock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'status' => ["Stock insuffisant pour {$product->name} : {$product->stock} disponible(s), {$item->quantity} requis."],
                        ]);
                    }
                }

                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $this->inventoryMovementService->adjust(
                            $products->get($item->product_id),
                            -$item->quantity,
                            'order_confirmation',
                            $request->user()->id,
                            $order,
                            'Déduction lors de la confirmation de la commande.',
                        );
                    }
                }
            }

            if ($data['status'] === 'cancelled' && in_array($order->status, ['confirmed', 'preparing', 'out_for_delivery'], true)) {
                $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $this->inventoryMovementService->adjust(
                            Product::findOrFail($item->product_id),
                            $item->quantity,
                            'order_cancellation',
                            $request->user()->id,
                            $order,
                            'Restitution lors de l’annulation avant livraison.',
                        );
                    }
                }
                if ($delivery && ! in_array($delivery->status, ['delivered', 'cancelled'], true)) {
                    $delivery->update([
                        'status' => 'cancelled',
                        'failure_reason' => null,
                        'location_sharing_active' => false,
                        'location_sharing_stopped_at' => now(),
                        'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
                    ]);
                    DeliveryStatusHistory::create([
                        'delivery_id' => $delivery->id,
                        'changed_by' => $request->user()->id,
                        'status' => 'cancelled',
                        'note' => 'Commande annulée par un administrateur.',
                    ]);
                }
            }
            if ($data['status'] === 'delivered') {
                $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();
                if ($delivery && ! in_array($delivery->status, ['delivered', 'cancelled'], true)) {
                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'location_sharing_active' => false,
                        'location_sharing_stopped_at' => now(),
                        'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
                    ]);
                    DeliveryStatusHistory::create([
                        'delivery_id' => $delivery->id,
                        'changed_by' => $request->user()->id,
                        'status' => 'delivered',
                        'note' => 'Remise confirmée par un administrateur.',
                    ]);
                }
            }
            if ($data['status'] === 'out_for_delivery') {
                $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();
                if ($delivery && $delivery->status === 'to_prepare') {
                    $delivery->update(['status' => 'ready_for_delivery']);
                    DeliveryStatusHistory::create([
                        'delivery_id' => $delivery->id,
                        'changed_by' => $request->user()->id,
                        'status' => 'ready_for_delivery',
                        'note' => 'Commande mise en cours de livraison par un administrateur.',
                    ]);
                }
            }

            $confirming = $data['status'] === 'confirmed';
            $cancelling = $data['status'] === 'cancelled';
            $cancelledAfterConfirmation = $cancelling && $order->status !== 'pending';
            $whatsappStatus = ! $order->whatsapp_consent
                ? 'skipped'
                : (app(WhatsAppProvider::class)->isEnabled() ? 'pending' : 'not_configured');

            $order->update([
                'status' => $data['status'],
                'email_status' => $confirming
                    ? 'pending'
                    : ($cancelledAfterConfirmation && in_array($order->email_status, ['pending', 'sending'], true)
                        ? 'failed'
                        : $order->email_status),
                'email_sent_at' => $confirming ? null : $order->email_sent_at,
                'email_attempts' => $confirming ? 0 : $order->email_attempts,
                'email_error' => $confirming ? null : ($cancelledAfterConfirmation && in_array($order->email_status, ['pending', 'sending'], true)
                    ? 'Commande annulée avant l’envoi.'
                    : $order->email_error),
                'whatsapp_status' => $confirming
                    ? $whatsappStatus
                    : ($cancelledAfterConfirmation && in_array($order->whatsapp_status, ['pending', 'sending'], true)
                        ? 'failed'
                        : $order->whatsapp_status),
                'whatsapp_sent_at' => $confirming ? null : $order->whatsapp_sent_at,
                'whatsapp_attempts' => $confirming ? 0 : $order->whatsapp_attempts,
                'whatsapp_error' => $confirming ? null : ($cancelledAfterConfirmation && in_array($order->whatsapp_status, ['pending', 'sending'], true)
                    ? 'Commande annulée avant l’envoi.'
                    : $order->whatsapp_error),
            ]);

            return [$order->fresh(['items', 'zone', 'user']), $confirming];
        });

        if ($newlyConfirmed) {
            $this->dispatchNotification($order, 'email');
            if ($order->whatsapp_status === 'pending') {
                $this->dispatchNotification($order, 'whatsapp');
            }
            $order->refresh();
        }

        return $order;
    }

    public function retryNotification(Request $request, int $id)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);
        $order = Order::findOrFail($id);

        if ($order->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'channel' => ['Les notifications ne peuvent être relancées que pour une commande confirmée.'],
            ]);
        }

        $channel = $data['channel'];
        if ($order->{$channel.'_status'} !== 'failed') {
            throw ValidationException::withMessages([
                'channel' => ['Seule une notification en échec peut être relancée.'],
            ]);
        }

        if ($channel === 'whatsapp' && ! $order->whatsapp_consent) {
            throw ValidationException::withMessages([
                'channel' => ['Le client n’a pas consenti à recevoir les messages WhatsApp.'],
            ]);
        }

        if ($channel === 'whatsapp' && ! app(WhatsAppProvider::class)->isEnabled()) {
            $order->updateQuietly(['whatsapp_status' => 'not_configured']);

            return response()->json($order->fresh(), 409);
        }

        $claimed = DB::table('orders')
            ->where('id', $order->id)
            ->where('status', 'confirmed')
            ->where($channel.'_status', 'failed')
            ->update([
                $channel.'_status' => 'pending',
                $channel.'_error' => null,
                'updated_at' => now(),
            ]);
        if ($claimed !== 1) {
            throw ValidationException::withMessages([
                'channel' => ['Cette notification a déjà été prise en charge.'],
            ]);
        }

        $this->dispatchNotification($order, $channel);

        return $order->fresh();
    }

    private function dispatchNotification(Order $order, string $channel): void
    {
        try {
            SendOrderNotification::dispatch($order->id, $channel)->afterCommit();
        } catch (Throwable $exception) {
            $this->orderNotificationService->markDispatchFailed($order, $channel);
            Log::warning('Order notification could not be queued', [
                'order_id' => $order->id,
                'channel' => $channel,
                'exception' => $exception::class,
            ]);
        }
    }
}
