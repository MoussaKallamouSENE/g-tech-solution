<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminDeliveryController extends Controller
{
    public function index()
    {
        return DeliveryResource::collection(
            Delivery::with([
                'driver:id,name',
                'order:id,number,status,customer_name,customer_phone,customer_address',
                'statusHistory.changedBy:id,name',
            ])->latest('updated_at')->paginate(50)
        );
    }

    public function drivers()
    {
        return User::where('role', 'driver')->orderBy('name')->get(['id', 'name']);
    }

    public function createDriver(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s.-]{8,30}$/'],
            'password' => ['required', 'string', Password::min(12)],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['role'] = 'driver';

        return response()->json(User::create($data), 201);
    }

    public function pendingOrders()
    {
        return Order::whereIn('status', ['confirmed', 'preparing', 'out_for_delivery'])
            ->whereDoesntHave('delivery')
            ->latest()
            ->get(['id', 'number', 'status', 'customer_name', 'customer_phone', 'customer_address']);
    }

    public function assign(Request $request, int $orderId)
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'driver')],
        ]);

        $delivery = DB::transaction(function () use ($data, $orderId, $request): Delivery {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if (! in_array($order->status, ['confirmed', 'preparing', 'out_for_delivery'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Seule une commande confirmée ou en cours de livraison peut être affectée.'],
                ]);
            }

            $delivery = Delivery::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($delivery && in_array($delivery->status, ['delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Une livraison terminée ne peut pas être réaffectée.'],
                ]);
            }

            if (! $delivery) {
                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $data['driver_id'],
                    'status' => 'to_prepare',
                    'assigned_at' => now(),
                ]);

                DeliveryStatusHistory::create([
                    'delivery_id' => $delivery->id,
                    'changed_by' => $request->user()->id,
                    'status' => $delivery->status,
                ]);
            } elseif ($delivery->driver_id !== (int) $data['driver_id']) {
                $delivery->update([
                    'driver_id' => $data['driver_id'],
                    'assigned_at' => now(),
                    'location_sharing_active' => false,
                    'location_sharing_stopped_at' => now(),
                    'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
                ]);
            }

            return $delivery->fresh([
                'driver:id,name',
                'order:id,number,status,customer_name,customer_phone,customer_address',
                'statusHistory.changedBy:id,name',
            ]);
        });

        return response()->json((new DeliveryResource($delivery))->resolve(), 201);
    }

    public function updateStatus(Request $request, int $deliveryId)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['ready_for_delivery'])],
        ]);

        $orderId = Delivery::query()->findOrFail($deliveryId)->order_id;
        $delivery = DB::transaction(function () use ($data, $orderId, $request): Delivery {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $delivery = Delivery::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($delivery->status !== 'to_prepare') {
                throw ValidationException::withMessages([
                    'status' => ['Seule une livraison à préparer peut être marquée prête.'],
                ]);
            }

            if (! in_array($order->status, ['confirmed', 'preparing', 'out_for_delivery'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['La commande doit être confirmée ou en préparation.'],
                ]);
            }

            $delivery->update(['status' => $data['status']]);
            if ($order->status === 'confirmed') {
                $order->update(['status' => 'preparing']);
            }
            DeliveryStatusHistory::create([
                'delivery_id' => $delivery->id,
                'changed_by' => $request->user()->id,
                'status' => $data['status'],
            ]);

            return $delivery->fresh([
                'driver:id,name',
                'order:id,number,status,customer_name,customer_phone,customer_address',
                'statusHistory.changedBy:id,name',
            ]);
        });

        return response()->json((new DeliveryResource($delivery))->resolve());
    }
}
