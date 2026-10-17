<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Services\InventoryMovementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminPurchaseReceiptController extends Controller
{
    public function __construct(private readonly InventoryMovementService $inventoryMovementService) {}

    public function index()
    {
        return PurchaseReceipt::query()
            ->with(['supplier:id,name', 'user:id,name', 'items'])
            ->latest('received_at')
            ->latest('id')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $totalCost = array_sum(array_map(
            static fn (array $item): int => $item['quantity'] * $item['unit_cost'],
            $data['items'],
        ));
        if ($totalCost > 9_007_199_254_740_991) {
            throw ValidationException::withMessages([
                'items' => ['Le montant total dépasse la limite de précision prise en charge.'],
            ]);
        }

        $existing = PurchaseReceipt::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->with(['supplier:id,name', 'user:id,name', 'items'])
            ->first();
        if ($existing) {
            return $this->replayResponse($existing, $data, $totalCost);
        }

        try {
            $receipt = DB::transaction(function () use ($data, $request, $totalCost) {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
                if (! $supplier->is_active) {
                    throw ValidationException::withMessages([
                        'supplier_id' => ['Ce fournisseur est désactivé et ne peut pas être utilisé pour un achat.'],
                    ]);
                }

                $productIds = collect($data['items'])->pluck('product_id')->sort()->values();
                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => ['Un ou plusieurs produits ne sont plus disponibles. Rechargez la page et réessayez.'],
                    ]);
                }

                $receipt = PurchaseReceipt::create([
                    'number' => 'ACH-'.Str::uuid(),
                    'idempotency_key' => $data['idempotency_key'],
                    'supplier_id' => $supplier->id,
                    'user_id' => $request->user()->id,
                    'supplier_reference' => $data['supplier_reference'] ?? null,
                    'received_at' => $data['received_at'],
                    'total_cost' => $totalCost,
                    'notes' => $data['notes'] ?? null,
                ]);
                $receipt->update(['number' => 'ACH-'.now()->format('Ymd').'-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT)]);

                foreach ($data['items'] as $item) {
                    /** @var Product $product */
                    $product = $products->get($item['product_id']);
                    $lineTotal = $item['quantity'] * $item['unit_cost'];

                    PurchaseReceiptItem::create([
                        'purchase_receipt_id' => $receipt->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_reference' => $product->reference,
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $lineTotal,
                    ]);

                    $this->inventoryMovementService->adjust(
                        $product,
                        $item['quantity'],
                        'purchase_receipt',
                        $request->user()->id,
                        note: "Réception {$receipt->number}.",
                        purchaseReceipt: $receipt,
                    );
                }

                return $receipt->load(['supplier:id,name', 'user:id,name', 'items']);
            });
        } catch (QueryException $exception) {
            $duplicate = PurchaseReceipt::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->with(['supplier:id,name', 'user:id,name', 'items'])
                ->first();
            if ($duplicate) {
                return $this->replayResponse($duplicate, $data, $totalCost);
            }

            throw $exception;
        }

        return response()->json($receipt, 201);
    }

    private function replayResponse(PurchaseReceipt $receipt, array $data, int $totalCost): JsonResponse
    {
        $expectedItems = collect($data['items'])
            ->mapWithKeys(fn (array $item): array => [
                $item['product_id'] => [
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ],
            ]);
        $storedItems = $receipt->items
            ->mapWithKeys(fn (PurchaseReceiptItem $item): array => [
                $item->product_id => [
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                ],
            ]);

        $matches = (int) $receipt->supplier_id === $data['supplier_id']
            && $receipt->supplier_reference === ($data['supplier_reference'] ?? null)
            && $receipt->received_at->toDateString() === Carbon::parse($data['received_at'])->toDateString()
            && $receipt->notes === ($data['notes'] ?? null)
            && (int) $receipt->total_cost === $totalCost
            && $storedItems->count() === $expectedItems->count()
            && $storedItems->all() === $expectedItems->all();

        if (! $matches) {
            abort(409, 'Cette clé de réception a déjà été utilisée avec des données différentes.');
        }

        return response()->json($receipt);
    }
}
