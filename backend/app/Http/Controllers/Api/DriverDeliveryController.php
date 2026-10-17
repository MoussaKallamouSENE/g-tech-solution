<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DriverDeliveryController extends Controller
{
    public function index(Request $request)
    {
        return DeliveryResource::collection(
            Delivery::where('driver_id', $request->user()->id)
                ->with([
                    'driver:id,name',
                    'order:id,number,status,customer_name,customer_phone,customer_address',
                    'statusHistory.changedBy:id,name',
                ])
                ->latest('updated_at')
                ->paginate(50)
        );
    }

    public function updateStatus(Request $request, int $deliveryId)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_delivery', 'delivered', 'failed'])],
            'reason' => ['required_if:status,failed', 'nullable', 'string', 'min:4', 'max:500'],
            'delivery_confirmed' => ['nullable', 'boolean'],
        ]);

        if ($data['status'] === 'delivered' && ($data['delivery_confirmed'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'delivery_confirmed' => ['Confirmez explicitement la remise au client.'],
            ]);
        }

        $orderId = Delivery::query()
            ->where('driver_id', $request->user()->id)
            ->whereKey($deliveryId)
            ->value('order_id');
        abort_if(! $orderId, 404);

        $delivery = DB::transaction(function () use ($data, $orderId, $request): Delivery {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $delivery = Delivery::query()
                ->where('order_id', $order->id)
                ->where('driver_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowed = match ($delivery->status) {
                'ready_for_delivery' => ['in_delivery'],
                'in_delivery' => ['delivered', 'failed'],
                'failed' => ['in_delivery'],
                default => [],
            };

            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Cette transition de livraison n’est pas autorisée.'],
                ]);
            }

            $changes = ['status' => $data['status']];
            if ($data['status'] === 'in_delivery') {
                $changes['picked_up_at'] ??= now();
                $changes['failure_reason'] = null;
            } elseif ($data['status'] === 'delivered') {
                if ($order->status !== 'out_for_delivery') {
                    throw ValidationException::withMessages([
                        'status' => ['La commande doit être en cours de livraison avant sa confirmation.'],
                    ]);
                }
                $changes['delivered_at'] = now();
                $changes['failure_reason'] = null;
            } else {
                $changes['failure_reason'] = $data['reason'];
            }

            if (in_array($data['status'], ['delivered', 'failed'], true)) {
                $changes['location_sharing_active'] = false;
                $changes['location_sharing_stopped_at'] = now();
                $changes['location_expires_at'] = now()->addDays(config('services.delivery.location_retention_days', 30));
            }

            $delivery->update($changes);
            if ($data['status'] === 'in_delivery' && $order->status === 'preparing') {
                $order->update(['status' => 'out_for_delivery']);
            } elseif ($data['status'] === 'delivered') {
                $order->update(['status' => 'delivered']);
            }

            DeliveryStatusHistory::create([
                'delivery_id' => $delivery->id,
                'changed_by' => $request->user()->id,
                'status' => $data['status'],
                'note' => $data['reason'] ?? null,
            ]);

            return $delivery->fresh([
                'driver:id,name',
                'order:id,number,status,customer_name,customer_phone,customer_address',
                'statusHistory.changedBy:id,name',
            ]);
        });

        return response()->json((new DeliveryResource($delivery))->resolve());
    }

    public function startLocationSharing(Request $request, int $deliveryId)
    {
        $delivery = DB::transaction(function () use ($request, $deliveryId): Delivery {
            $delivery = Delivery::where('driver_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);
            if ($delivery->status !== 'in_delivery') {
                throw ValidationException::withMessages([
                    'status' => ['La livraison doit être en cours avant de démarrer le partage GPS.'],
                ]);
            }

            $delivery->update([
                'location_sharing_active' => true,
                'location_sharing_started_at' => now(),
                'location_sharing_stopped_at' => null,
                'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
            ]);

            return $delivery->fresh();
        });

        return response()->json((new DeliveryResource($delivery->fresh([
            'driver:id,name',
            'order:id,number,status,customer_name,customer_phone,customer_address',
        ])))->resolve());
    }

    public function stopLocationSharing(Request $request, int $deliveryId)
    {
        $delivery = DB::transaction(function () use ($request, $deliveryId): Delivery {
            $delivery = Delivery::where('driver_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);
            $delivery->update([
                'location_sharing_active' => false,
                'location_sharing_stopped_at' => now(),
                'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
            ]);

            return $delivery->fresh();
        });

        return response()->json((new DeliveryResource($delivery->fresh([
            'driver:id,name',
            'order:id,number,status,customer_name,customer_phone,customer_address',
        ])))->resolve());
    }

    public function updateLocation(Request $request, int $deliveryId)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'between:0,1000'],
        ]);

        $delivery = DB::transaction(function () use ($data, $request, $deliveryId): Delivery {
            $delivery = Delivery::where('driver_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($deliveryId);
            if (! $delivery->location_sharing_active || $delivery->status !== 'in_delivery') {
                throw ValidationException::withMessages([
                    'location' => ['Le partage GPS n’est pas actif pour cette livraison.'],
                ]);
            }
            if (! $delivery->location_expires_at || $delivery->location_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'location' => ['Le partage GPS a expiré. Redémarrez le suivi depuis votre espace livreur.'],
                ]);
            }
            if ($delivery->location_updated_at && $delivery->location_updated_at->gt(now()->subSeconds(10))) {
                abort(429, 'Position reçue trop récemment.');
            }

            $delivery->update([
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'location_accuracy' => $data['accuracy'] ?? null,
                'location_updated_at' => now(),
                'location_expires_at' => now()->addDays(config('services.delivery.location_retention_days', 30)),
            ]);

            return $delivery->fresh();
        });

        return response()->json((new DeliveryResource($delivery->fresh([
            'driver:id,name',
            'order:id,number,status,customer_name,customer_phone,customer_address',
        ])))->resolve());
    }
}
