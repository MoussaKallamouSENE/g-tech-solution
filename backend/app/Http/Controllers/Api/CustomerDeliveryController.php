<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerDeliveryResource;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerDeliveryController extends Controller
{
    public function show(Request $request, int $orderId)
    {
        abort_unless($request->user()->role === 'customer', 403);

        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('delivery.driver:id,name')
            ->findOrFail($orderId);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
            ],
            'delivery' => $order->delivery
                ? (new CustomerDeliveryResource($order->delivery))->resolve()
                : null,
        ]);
    }
}
