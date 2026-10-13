<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

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
            'repairs_new' => \App\Models\MaintenanceRequest::where('status', 'new')->count(),
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
        $query = Order::withCount('items');

        $status = $request->query('status');
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('search', ''));
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

        DB::transaction(function () use ($id, $data) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);

            if ($order->status === $data['status']) {
                return;
            }

            if (in_array($order->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Cette commande est terminée : son statut ne peut plus changer.'],
                ]);
            }

            if ($data['status'] === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update(['status' => $data['status']]);
        });

        return Order::with(['items', 'zone'])->findOrFail($id);
    }
}