<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardPurchaseMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_month_to_date_purchases_and_active_stock_units(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
        $admin = User::create([
            'name' => 'Admin dashboard',
            'email' => 'dashboard-admin@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
        $supplier = Supplier::create(['name' => 'Fournisseur test']);
        $activeProduct = $this->createProduct('Produit actif', 5, true);
        $secondActiveProduct = $this->createProduct('Autre produit actif', 3, true);
        $this->createProduct('Produit masqué', 100, false);

        $oldReceipt = $this->createReceipt($supplier, 'ACH-SEP', '2026-09-30', 100000, $activeProduct, 1);
        $this->createReceipt($supplier, 'ACH-OCT-1', '2026-10-01', 20000, $activeProduct, 1);
        $latestReceipt = $this->createReceipt($supplier, 'ACH-OCT-2', '2026-10-14', 50000, $secondActiveProduct, 2);

        $this->actingAs($admin, 'sanctum')->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('purchases_month_total', 70000)
            ->assertJsonPath('purchases_month_count', 2)
            ->assertJsonPath('stock_units_available', 8)
            ->assertJsonPath('recent_purchases.0.id', $latestReceipt->id)
            ->assertJsonPath('recent_purchases.0.supplier.name', $supplier->name)
            ->assertJsonPath('recent_purchases.0.items_count', 1);

        $this->assertNotSame($oldReceipt->id, $latestReceipt->id);
    }

    private function createProduct(string $name, int $stock, bool $active): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'dashboard-metrics'],
            ['name' => 'Indicateurs', 'is_active' => true],
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'reference' => strtoupper(substr($name, 0, 3)).'-'.uniqid(),
            'price' => 1000,
            'stock' => $stock,
            'stock_alert_threshold' => 2,
            'state' => 'neuf',
            'is_active' => $active,
        ]);
    }

    private function createReceipt(
        Supplier $supplier,
        string $number,
        string $date,
        int $cost,
        Product $product,
        int $quantity,
    ): PurchaseReceipt {
        $receipt = PurchaseReceipt::create([
            'number' => $number,
            'supplier_id' => $supplier->id,
            'received_at' => $date,
            'total_cost' => $cost,
        ]);
        $receipt->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_reference' => $product->reference,
            'quantity' => $quantity,
            'unit_cost' => $cost,
            'total_cost' => $cost,
        ]);

        return $receipt;
    }
}
