<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Compteur utilisé pour générer des numéros de commande uniques.
     */
    private static int $sequence = 0;

    public function test_only_the_order_customer_or_an_admin_can_download_an_invoice_with_real_order_values(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $order = $this->createOrder($customer);
        $url = '/api/orders/'.$order->id.'/invoice';

        $this->getJson($url)->assertUnauthorized();

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson($url)
            ->assertNotFound();

        $this->actingAs($driver, 'sanctum')
            ->getJson($url)
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename='.strtolower($order->invoice_number).'.pdf')
            ->assertSee('%PDF-', false);

        $this->actingAs($admin, 'sanctum')
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-', false);
    }

    public function test_invoice_uses_persisted_order_amounts_and_snapshot_warranty_not_current_product_data(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrder($customer);
        $order->items()->firstOrFail()->update([
            'product_specs_snapshot' => ['processor' => 'Core i7', 'ram' => '16 Go'],
            'warranty_conditions_snapshot' => 'Garantie spécifique enregistrée à la vente.',
        ]);

        $html = $this->renderInvoice($order->fresh(['items', 'zone']));

        // La référence réelle de la facture et le numéro de commande sont affichés.
        $this->assertStringContainsString($order->invoice_number, $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('FA-'.substr($order->number, 3), $html);

        // Les montants affichés proviennent de la commande enregistrée.
        $this->assertStringContainsString($this->money(1_200_000).' FCFA', $html);
        $this->assertStringContainsString($this->money(25_000).' FCFA', $html);
        $this->assertStringContainsString($this->money(1_225_000).' FCFA', $html);

        // L'instantané de vente et les conditions de garantie sont restitués.
        $this->assertStringContainsString('Garantie spécifique enregistrée à la vente.', $html);
        $this->assertStringContainsString('carte mère pendant 6 mois', $html);
        $this->assertStringContainsString('Core i7', $html);
        $this->assertStringContainsString('16 Go', $html);

        // Le paiement n'est pas suivi par le système.
        $this->assertStringContainsString('Montant payé : non enregistré dans le système', $html);
        $this->assertStringContainsString('Reste à payer : non calculable (paiement non suivi)', $html);
        $this->assertStringContainsString('droits légaux du client', $html);
        $this->assertStringNotContainsString('Reste à payer : '.$this->money(1_225_000).' FCFA', $html);
    }

    public function test_legacy_invoice_shows_available_product_characteristics_as_current_not_historical(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrder($customer);
        $category = Category::create([
            'name' => 'Ordinateurs',
            'slug' => 'ordinateurs-'.uniqid(),
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ordinateur portable',
            'slug' => 'portable-'.uniqid(),
            'reference' => 'REF-'.uniqid(),
            'brand' => 'G-Tech',
            'description' => 'Portable professionnel',
            'price' => 600_000,
            'stock' => 1,
            'stock_alert_threshold' => 1,
            'processor' => 'Core i9',
            'ram' => '32 Go',
            'storage' => 'SSD 1 To',
            'graphics_card' => 'RTX 4060',
            'screen' => '15 pouces',
            'state' => 'neuf',
            'is_active' => true,
        ]);
        $item = $order->items()->firstOrFail();
        $item->update(['product_id' => $product->id, 'product_specs_snapshot' => null]);

        $html = $this->renderInvoice($order->fresh(['items.product', 'zone']));

        // Sans instantané, les caractéristiques actuelles sont signalées comme telles.
        $this->assertStringContainsString('Caractéristiques actuelles — non archivées lors de la vente', $html);
        $this->assertStringContainsString('Core i9', $html);
        $this->assertStringContainsString('32 Go', $html);
        $this->assertStringContainsString('SSD 1 To', $html);
        $this->assertStringContainsString('RTX 4060', $html);
        $this->assertStringContainsString('15 pouces', $html);
    }

    public function test_invoice_prints_shop_details_only_when_they_are_configured(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrder($customer);

        config([
            'shop.address' => '',
            'shop.phone' => '',
            'shop.email' => '',
            'shop.ninea' => '',
            'shop.rccm' => '',
        ]);

        $html = $this->renderInvoice($order);

        $this->assertStringContainsString('Dakar, Sénégal', $html);
        $this->assertStringNotContainsString('NINEA', $html);
        $this->assertStringNotContainsString('RCCM', $html);

        config([
            'shop.address' => 'Plateau',
            'shop.phone' => '+221 77 000 00 00',
            'shop.email' => 'contact@gtech.sn',
            'shop.ninea' => 'NINEA123',
            'shop.rccm' => 'RCCM456',
        ]);

        $html = $this->renderInvoice($order);

        $this->assertStringContainsString('Plateau, Dakar, Sénégal', $html);
        $this->assertStringContainsString('+221 77 000 00 00 · contact@gtech.sn', $html);
        $this->assertStringContainsString('NINEA : NINEA123 · RCCM : RCCM456', $html);
    }

    public function test_invoice_access_is_limited_to_the_order_owner_and_administrators(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->createOrder($customer);
        $cancelledOrder = $this->createOrder($customer, 'cancelled');

        // Le propriétaire de la commande reçoit le PDF.
        $this->actingAs($customer, 'sanctum')
            ->get('/api/orders/'.$order->id.'/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-', false);

        // L'administrateur reçoit le PDF.
        $this->actingAs($admin, 'sanctum')
            ->get('/api/orders/'.$order->id.'/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-', false);

        // Un autre client n'a pas accès à cette commande.
        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/invoice')
            ->assertNotFound();

        // Une commande annulée ne produit pas de facture.
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$cancelledOrder->id.'/invoice')
            ->assertUnprocessable();
    }

    public function test_cancelled_orders_do_not_produce_invoice_pdfs(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrder($customer, 'cancelled');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/invoice')
            ->assertUnprocessable();
    }

    /**
     * Rend le modèle de facture comme le fait OrderController::invoice().
     */
    private function renderInvoice(Order $order): string
    {
        return view('invoice', [
            'order' => $order,
            'company' => config('invoice.company'),
        ])->render();
    }

    /**
     * Format monétaire identique à celui du modèle de facture.
     */
    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', "\u{00A0}");
    }

    private function createOrder(User $customer, string $status = 'confirmed'): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'number' => 'GT-'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'customer_name' => 'Aminata Ndiaye',
            'customer_phone' => '+221 77 123 45 67',
            'customer_address' => 'Dakar, Plateau, Rue 12',
            'subtotal' => 1_200_000,
            'delivery_fee' => 25_000,
            'total' => 1_225_000,
            'payment_method' => 'cash_on_delivery',
            'status' => $status,
        ]);

        $order->items()->create([
            'product_name' => 'Ordinateur portable',
            'unit_price' => 600_000,
            'quantity' => 2,
            'total' => 1_200_000,
            'product_specs_snapshot' => ['processor' => 'Core i5'],
        ]);

        return $order->fresh(['items', 'zone']);
    }
}
