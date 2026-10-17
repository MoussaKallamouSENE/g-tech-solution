<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\MaintenanceRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $start = now()->subDays(6)->startOfDay();
        $monthStart = now()->startOfMonth()->toDateString();

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
            'deliveries_in_delivery' => Delivery::where('status', 'in_delivery')->count(),
            'gps_active' => Delivery::where('location_sharing_active', true)
                ->where('location_expires_at', '>', now())
                ->count(),
            'low_stock' => (clone $lowStock)->count(),
            'stock_units_available' => (int) Product::where('is_active', true)->sum('stock'),
            'purchases_month_total' => (int) PurchaseReceipt::whereDate('received_at', '>=', $monthStart)
                ->whereDate('received_at', '<=', today())
                ->sum('total_cost'),
            'purchases_month_count' => PurchaseReceipt::whereDate('received_at', '>=', $monthStart)
                ->whereDate('received_at', '<=', today())
                ->count(),
            'top_products' => $topProducts,
            'recent_orders' => Order::latest()->orderByDesc('id')->limit(5)
                ->get(['id', 'number', 'customer_name', 'total', 'status', 'created_at']),
            'low_stock_products' => (clone $lowStock)->orderBy('stock')->limit(5)
                ->get(['id', 'name', 'stock', 'stock_alert_threshold']),
            'recent_purchases' => PurchaseReceipt::with('supplier:id,name')
                ->withCount('items')
                ->latest('received_at')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'number', 'supplier_id', 'received_at', 'total_cost']),
        ];
    }
}
