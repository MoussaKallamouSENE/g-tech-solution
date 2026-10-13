<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $start = now()->subDays(6)->startOfDay();

        $byDay = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $start)
            ->get(['total', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->toDateString());

        $salesByDay = collect(range(6, 0))->map(function ($i) use ($byDay) {
            $date = now()->subDays($i)->toDateString();
            $day = $byDay->get($date, collect());

            return ['date' => $date, 'total' => (int) $day->sum('total'), 'count' => $day->count()];
        })->values();

        $previous = (int) Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->where('created_at', '<', $start)
            ->sum('total');

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get([DB::raw('order_items.product_name as name'), DB::raw('SUM(order_items.quantity) as sold')]);

        $lowStock = Product::where('is_active', true)->whereColumn('stock', '<=', 'stock_alert_threshold');

        return [
            'sales_by_day' => $salesByDay,
            'previous_week_total' => $previous,
            'orders_total' => Order::count(),
            'orders_pending' => Order::where('status', 'pending')->count(),
            'repairs_new' => MaintenanceRequest::where('status', 'new')->count(),
            'low_stock' => (clone $lowStock)->count(),
            'top_products' => $topProducts,
            'recent_orders' => Order::latest()->orderByDesc('id')->limit(5)
                ->get(['id', 'number', 'customer_name', 'total', 'status', 'created_at']),
            'low_stock_products' => (clone $lowStock)->orderBy('stock')->limit(5)
                ->get(['id', 'name', 'stock', 'stock_alert_threshold']),
        ];
    }
}