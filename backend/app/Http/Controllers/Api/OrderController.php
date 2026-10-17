<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('user_id', $request->user()->id)
            ->withCount('items');

        $status = $request->query('status');
        if (in_array($status, ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', 'newest');

        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
                break;
            case 'total_asc':
                $query->orderBy('total', 'asc')->orderBy('id', 'desc');
                break;
            case 'total_desc':
                $query->orderBy('total', 'desc')->orderBy('id', 'desc');
                break;
            case 'newest':
            default:
                $query->latest()->orderByDesc('id');
                break;
        }

        return $query->get();
    }

    public function invoice(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'customer'], true), 403);
        $query = Order::with(['items.product', 'zone']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $order = $query->findOrFail($id);

        abort_if($order->status === 'cancelled', 422, "Une commande annulée n'a pas de facture.");

        return Pdf::loadView('invoice', [
            'order' => $order,
            'company' => config('invoice.company'),
        ])
            ->setOptions(['isRemoteEnabled' => false])
            ->setPaper('a4')
            ->download(strtolower($order->invoice_number).'.pdf');
    }

    public function show(Request $request, int $id)
    {
        return Order::where('user_id', $request->user()->id)
            ->with(['items', 'zone'])
            ->findOrFail($id);
    }

    public function cancel(Request $request, int $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cette commande ne peut pas être annulée.'],
            ]);
        }

        $order->update(['status' => 'cancelled']);

        // Restituer le stock si la commande était confirmée
        if ($order->status === 'confirmed' && $order->items) {
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                        InventoryMovement::create([
                            'product_id' => $product->id,
                            'order_id' => $order->id,
                            'user_id' => $request->user()->id,
                            'type' => 'order_cancellation',
                            'quantity_change' => $item->quantity,
                            'stock_before' => $product->stock,
                            'stock_after' => $product->stock + $item->quantity,
                            'note' => 'Restitution lors de l’annulation par le client.',
                        ]);
                    }
                }
            }
        }

        return response()->json($order->fresh(['items', 'zone']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'delivery_zone_id' => ['required', 'integer', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'customer_phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s.-]{8,30}$/'],
            'whatsapp_consent' => ['sometimes', 'boolean'],
            'customer_address' => ['required', 'string', 'min:10', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Votre panier est vide.',
            'items.*.quantity.max' => 'Une quantité ne peut pas dépasser 10 unités pour un même article.',
            'delivery_zone_id.required' => 'Choisissez votre zone de livraison.',
            'delivery_zone_id.exists' => "Cette zone de livraison n'est pas disponible.",
            'customer_name.required' => 'Le nom est obligatoire.',
            'customer_name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'customer_phone.required' => 'Le téléphone est obligatoire pour la livraison.',
            'customer_phone.min' => 'Le numéro de téléphone semble incomplet.',
            'customer_phone.regex' => 'Le numéro de téléphone contient des caractères invalides.',
            'customer_address.required' => "L'adresse de livraison est obligatoire.",
            'customer_address.min' => "L'adresse de livraison doit contenir au moins 10 caractères.",
        ]);
        if (($data['whatsapp_consent'] ?? false)
            && ! preg_match('/^\+[1-9][0-9]{7,14}$/', preg_replace('/[\s().-]+/', '', $data['customer_phone']) ?? '')) {
            throw ValidationException::withMessages([
                'customer_phone' => ['Pour WhatsApp, utilisez un numéro au format international, par exemple +221XXXXXXXXX.'],
            ]);
        }

        $order = DB::transaction(function () use ($data, $request) {
            $zone = DeliveryZone::findOrFail($data['delivery_zone_id']);
            $lines = [];
            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $product = Product::where('is_active', true)->find($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ["Un produit de votre panier n'est plus disponible."],
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuffisant pour {$product->name} : {$product->stock} disponible(s), {$item['quantity']} requis."],
                    ]);
                }

                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'total' => $lineTotal,
                    'product_specs_snapshot' => array_filter([
                        'reference' => $product->reference,
                        'brand' => $product->brand,
                        'description' => $product->description,
                        'processor' => $product->processor,
                        'ram' => $product->ram,
                        'storage' => $product->storage,
                        'graphics_card' => $product->graphics_card,
                        'screen' => $product->screen,
                        'state' => $product->state,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'warranty_conditions_snapshot' => $product->warranty_conditions,
                ];
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'delivery_zone_id' => $zone->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'whatsapp_consent' => $data['whatsapp_consent'] ?? false,
                'customer_address' => $data['customer_address'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'delivery_fee' => $zone->fee,
                'total' => $subtotal + $zone->fee,
                'payment_method' => 'cash_on_delivery',
                'status' => 'pending',
            ]);

            $order->update([
                'number' => 'GT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ]);
            $order->items()->createMany($lines);

            return $order;
        });

        return response()->json($order->load(['items', 'zone']), 201);
    }
}