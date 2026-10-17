<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    public function adjust(
        Product $product,
        int $quantityChange,
        string $type,
        ?int $userId = null,
        ?Order $order = null,
        ?string $note = null,
        ?PurchaseReceipt $purchaseReceipt = null,
    ): Product {
        if ($quantityChange === 0) {
            return $product;
        }

        $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
        $stockBefore = $lockedProduct->stock;
        $stockAfter = $stockBefore + $quantityChange;

        if ($stockAfter < 0) {
            throw ValidationException::withMessages([
                'stock' => ['Le mouvement demandé ferait passer le stock sous zéro.'],
            ]);
        }
        if ($stockAfter > 4_294_967_295) {
            throw ValidationException::withMessages([
                'stock' => ['La quantité dépasse la capacité maximale de stockage pour ce produit.'],
            ]);
        }

        $lockedProduct->update(['stock' => $stockAfter]);

        InventoryMovement::create([
            'product_id' => $lockedProduct->id,
            'order_id' => $order?->id,
            'purchase_receipt_id' => $purchaseReceipt?->id,
            'user_id' => $userId,
            'product_name' => $lockedProduct->name,
            'product_reference' => $lockedProduct->reference,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'note' => $note,
        ]);

        return $lockedProduct;
    }

    public function setStock(
        Product $product,
        int $stock,
        string $type,
        ?int $userId = null,
        ?string $note = null,
    ): Product {
        return DB::transaction(function () use ($product, $stock, $type, $userId, $note): Product {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            return $this->adjust(
                $lockedProduct,
                $stock - $lockedProduct->stock,
                $type,
                $userId,
                note: $note,
            );
        });
    }
}
