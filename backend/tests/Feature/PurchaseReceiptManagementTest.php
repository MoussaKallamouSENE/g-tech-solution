<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseReceiptManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_records_a_supplier_and_multi_product_receipt_with_stock_movements(): void
    {
        [$admin, $supplier, $firstProduct, $secondProduct] = $this->prepareScenario();

        $response = $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $this->payload($supplier, [
                ['product_id' => $firstProduct->id, 'quantity' => 3, 'unit_cost' => 75000],
                ['product_id' => $secondProduct->id, 'quantity' => 2, 'unit_cost' => 125000],
            ]))
            ->assertCreated()
            ->assertJsonPath('supplier.id', $supplier->id)
            ->assertJsonPath('total_cost', 475000)
            ->assertJsonCount(2, 'items');
        $receiptId = $response->json('id');

        $this->assertSame(8, $firstProduct->fresh()->stock);
        $this->assertSame(7, $secondProduct->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $firstProduct->id,
            'purchase_receipt_id' => $receiptId,
            'type' => 'purchase_receipt',
            'quantity_change' => 3,
            'stock_before' => 5,
            'stock_after' => 8,
        ]);
        $this->assertDatabaseHas('purchase_receipt_items', [
            'product_id' => $secondProduct->id,
            'product_name' => $secondProduct->name,
            'product_reference' => $secondProduct->reference,
            'quantity' => 2,
            'unit_cost' => 125000,
            'total_cost' => 250000,
        ]);
    }

    public function test_replaying_the_same_receipt_key_does_not_increase_stock_twice(): void
    {
        [$admin, $supplier, $product] = $this->prepareScenario();
        $payload = $this->payload($supplier, [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 1000],
        ]);

        $firstResponse = $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $payload)->assertCreated();
        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $payload)->assertOk();

        $changedPayload = $payload;
        $changedPayload['items'][0]['quantity'] = 3;
        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $changedPayload)
            ->assertConflict()
            ->assertJsonPath('message', 'Cette clé de réception a déjà été utilisée avec des données différentes.');

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(1, InventoryMovement::where('purchase_receipt_id', $firstResponse->json('id'))->count());
    }

    public function test_receipt_validation_and_stock_overflow_leave_no_partial_purchase(): void
    {
        [$admin, $supplier, $regularProduct, $overflowProduct] = $this->prepareScenario();
        $overflowProduct->update(['stock' => 4_294_967_295]);

        $payload = $this->payload($supplier, [
            ['product_id' => $regularProduct->id, 'quantity' => 2, 'unit_cost' => 1000],
            ['product_id' => $overflowProduct->id, 'quantity' => 1, 'unit_cost' => 1000],
        ]);

        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.stock.0', 'La quantité dépasse la capacité maximale de stockage pour ce produit.');

        $this->assertSame(5, $regularProduct->fresh()->stock);
        $this->assertSame(4_294_967_295, $overflowProduct->fresh()->stock);
        $this->assertSame(0, PurchaseReceipt::count());
        $this->assertSame(0, InventoryMovement::where('type', 'purchase_receipt')->count());
    }

    public function test_purchase_routes_are_restricted_to_administrators(): void
    {
        [, $supplier, $product] = $this->prepareScenario();
        $customer = User::create([
            'name' => 'Client',
            'email' => 'client@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'customer',
        ]);

        $this->actingAs($customer, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/purchase-receipts', $this->payload($supplier, [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1000],
            ]))
            ->assertForbidden();

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, PurchaseReceipt::count());
    }

    public function test_supplier_creation_validates_contact_information(): void
    {
        [$admin] = $this->prepareScenario();

        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/suppliers', [
                'name' => 'Fournisseur test',
                'email' => 'adresse-invalide',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, Supplier::count());
    }

    private function prepareScenario(): array
    {
        $admin = User::create([
            'name' => 'Admin achats',
            'email' => 'admin-achats-'.uniqid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
        $supplier = Supplier::create(['name' => 'Fournisseur Dakar']);
        $category = Category::create([
            'name' => 'Informatique',
            'slug' => 'informatique-'.uniqid(),
            'is_active' => true,
        ]);

        $products = collect(['Ordinateur', 'Souris'])->map(function (string $name) use ($category) {
            return Product::create([
                'category_id' => $category->id,
                'name' => $name,
                'slug' => str($name)->slug().'-'.uniqid(),
                'reference' => strtoupper(substr($name, 0, 2)).'-'.uniqid(),
                'price' => 100000,
                'stock' => 5,
                'stock_alert_threshold' => 2,
                'state' => 'neuf',
                'is_active' => true,
            ]);
        });

        return array_merge([$admin, $supplier], $products->all());
    }

    private function payload(Supplier $supplier, array $items): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'supplier_reference' => 'FACT-2026-01',
            'received_at' => now()->toDateString(),
            'items' => $items,
        ];
    }
}
