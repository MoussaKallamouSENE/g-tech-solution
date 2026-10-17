<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_filters_and_sort_are_applied_to_real_active_products(): void
    {
        $laptops = $this->createCategory('Ordinateurs portables');
        $accessories = $this->createCategory('Accessoires');
        $this->createProduct($laptops, 'Dell compact', 125000, 'Dell', 3);
        $mostExpensiveMatch = $this->createProduct($laptops, 'Dell pro', 175000, 'Dell', 2);
        $this->createProduct($laptops, 'Dell bureau', 250000, 'Dell', 0);
        $this->createProduct($laptops, 'HP portable', 180000, 'HP', 5);
        $this->createProduct($accessories, 'Dell souris', 10000, 'Dell', 20);
        $this->createProduct($laptops, 'Produit masqué', 50000, 'Dell', 5, false);

        $this->getJson('/api/products?category='.$laptops->slug.'&brand=Dell&min_price=100000&max_price=200000&in_stock=1&sort=price_desc')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $mostExpensiveMatch->id)
            ->assertJsonPath('data.0.category.name', $laptops->name)
            ->assertJsonPath('data.0.image_url', null);
    }

    public function test_catalogue_paginates_server_side_and_preserves_valid_filters(): void
    {
        $category = $this->createCategory('Ordinateurs');
        for ($index = 1; $index <= 13; $index++) {
            $this->createProduct($category, 'Produit '.$index, $index * 1000);
        }

        $response = $this->getJson('/api/products?category='.$category->slug.'&sort=price_asc&page=2')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('per_page', 12)
            ->assertJsonPath('total', 13)
            ->assertJsonPath('data.0.name', 'Produit 13');
        $this->assertStringContainsString('category='.$category->slug, $response->json('first_page_url'));
    }

    public function test_catalogue_rejects_invalid_filter_values_and_price_ranges(): void
    {
        $this->getJson('/api/products?min_price=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['min_price']);

        $this->getJson('/api/products?min_price=200000&max_price=100000')
            ->assertUnprocessable()
            ->assertJsonPath('errors.max_price.0', 'Le prix maximum doit être supérieur ou égal au prix minimum.');

        $this->getJson('/api/products?state=broken')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    }

    public function test_admin_can_configure_product_specific_warranty_conditions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = $this->createCategory('Ordinateurs');
        $product = $this->createProduct($category, 'Portable sous garantie', 250000);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/products/'.$product->id, [
                'category_id' => $category->id,
                'name' => $product->name,
                'reference' => $product->reference,
                'brand' => $product->brand,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'stock_alert_threshold' => $product->stock_alert_threshold,
                'processor' => $product->processor,
                'ram' => $product->ram,
                'storage' => $product->storage,
                'graphics_card' => null,
                'screen' => null,
                'warranty_conditions' => 'Garantie constructeur de 12 mois sur la carte mère.',
                'state' => 'neuf',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('warranty_conditions', 'Garantie constructeur de 12 mois sur la carte mère.');
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function createProduct(
        Category $category,
        string $name,
        int $price,
        ?string $brand = 'Dell',
        int $stock = 1,
        bool $active = true,
    ): Product {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'reference' => strtoupper(substr(str($name)->slug(), 0, 5)).'-'.uniqid(),
            'brand' => $brand,
            'description' => 'Description réelle de test',
            'price' => $price,
            'stock' => $stock,
            'stock_alert_threshold' => 2,
            'processor' => 'Intel Core i5',
            'ram' => '16 Go',
            'storage' => 'SSD 512 Go',
            'state' => 'neuf',
            'is_active' => $active,
        ]);
    }
}
