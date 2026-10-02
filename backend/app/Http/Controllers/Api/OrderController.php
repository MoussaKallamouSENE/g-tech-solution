<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Order::where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    public function show(Request $request, int $id)
    {
        return Order::where('user_id', $request->user()->id)
            ->with(['items', 'zone'])
            ->findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'delivery_zone_id' => ['required', 'integer', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Votre panier est vide.',
            'delivery_zone_id.required' => 'Choisissez votre zone de livraison.',
            'delivery_zone_id.exists' => "Cette zone de livraison n'est pas disponible.",
            'customer_name.required' => 'Le nom est obligatoire.',
            'customer_phone.required' => 'Le téléphone est obligatoire pour la livraison.',
            'customer_address.required' => "L'adresse de livraison est obligatoire.",
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $zone = DeliveryZone::findOrFail($data['delivery_zone_id']);
            $lines = [];
            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $product = Product::where('is_active', true)->lockForUpdate()->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ["Un produit de votre panier n'est plus disponible."],
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuffisant pour {$product->name} : il en reste {$product->stock}."],
                    ]);
                }

                $product->decrement('stock', $item['quantity']);

                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'total' => $lineTotal,
                ];
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'delivery_zone_id' => $zone->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_address' => $data['customer_address'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'delivery_fee' => $zone->fee,
                'total' => $subtotal + $zone->fee,
                'payment_method' => 'cash_on_delivery',
                'status' => 'pending',
            ]);

            $order->update(['number' => 'GT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            $order->items()->createMany($lines);

            return $order;
        });

        return response()->json($order->load(['items', 'zone']), 201);
    }
}