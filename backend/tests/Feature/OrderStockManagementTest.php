<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Jobs\SendOrderNotification;
use App\Notifications\OrderConfirmedNotification;
use App\Services\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderStockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_order_keeps_stock_until_confirmation(): void
    {
        [$admin, $product] = $this->prepareScenario();

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Alice Client',
            'customer_phone' => '+221 77 001 02 03',
            'customer_address' => 'Dakar, Médina, Rue 12',
            'subtotal' => 2500,
            'delivery_fee' => 1000,
            'total' => 3500,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 2000,
        ]);

        $this->assertSame(5, Product::find($product->id)->stock);
    }

    public function test_new_orders_receive_unique_invoice_numbers_and_product_sale_snapshots(): void
    {
        [$customer, $product] = $this->prepareScenario();
        $product->update([
            'processor' => 'Ryzen 7',
            'ram' => '16 Go',
            'warranty_conditions' => 'Garantie fabricant de 12 mois sur la carte mère.',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'delivery_zone_id' => DeliveryZone::first()->id,
                'customer_name' => 'Alice Client',
                'customer_phone' => '+221 77 001 02 03',
                'customer_address' => 'Dakar, Médina, Rue 12',
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.product_specs_snapshot.processor', 'Ryzen 7')
            ->assertJsonPath('items.0.product_specs_snapshot.ram', '16 Go')
            ->assertJsonPath('items.0.warranty_conditions_snapshot', 'Garantie fabricant de 12 mois sur la carte mère.');

        $order = Order::findOrFail($response->json('id'));
        $this->assertMatchesRegularExpression('/^GTS-FA-\d{4}-[A-Z0-9]{16}$/', $order->invoice_number);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'invoice_number' => $order->invoice_number,
        ]);

        $product->update(['warranty_conditions' => 'Nouvelle garantie applicable aux ventes futures.']);
        $this->assertSame(
            'Garantie fabricant de 12 mois sur la carte mère.',
            $order->items()->firstOrFail()->warranty_conditions_snapshot,
        );
    }

    public function test_customer_cannot_create_order_when_stock_is_insufficient(): void
    {
        [$user, $product] = $this->prepareScenario(1);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/orders', [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]],
                'delivery_zone_id' => DeliveryZone::first()->id,
                'customer_name' => 'Alice Client',
                'customer_phone' => '+221 77 001 02 03',
                'customer_address' => 'Dakar, Médina, Rue 12',
                'notes' => 'Livraison rapide',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Stock insuffisant pour Portable Test : 1 disponible(s), 2 requis.');

        $this->assertDatabaseMissing('orders', ['customer_name' => 'Alice Client']);
    }

    public function test_customer_orders_can_be_filtered_and_sorted(): void
    {
        $user = User::factory()->create();
        DeliveryZone::create([
            'name' => 'Dakar Centre',
            'fee' => 1000,
            'is_active' => true,
        ]);

        $older = Order::create([
            'user_id' => $user->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Alice Client',
            'customer_phone' => '+221 77 001 02 03',
            'customer_address' => 'Dakar, Médina, Rue 12',
            'subtotal' => 2000,
            'delivery_fee' => 500,
            'total' => 2500,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);

        $newer = Order::create([
            'user_id' => $user->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Bob Client',
            'customer_phone' => '+221 76 001 02 03',
            'customer_address' => 'Dakar, Plateau, Rue 20',
            'subtotal' => 4000,
            'delivery_fee' => 1000,
            'total' => 5000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'confirmed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/orders?status=pending&sort=oldest')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $older->id);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/orders?sort=total_desc')
            ->assertOk()
            ->assertJsonPath('0.id', $newer->id)
            ->assertJsonPath('1.id', $older->id);
    }

    public function test_admin_order_list_validates_filters_searches_and_requires_admin_access(): void
    {
        [$admin] = $this->prepareScenario();

        $matchingOrder = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Aminata Ndiaye',
            'customer_phone' => '+221 77 111 22 33',
            'customer_address' => 'Dakar, Plateau',
            'subtotal' => 2000,
            'delivery_fee' => 1000,
            'total' => 3000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);
        Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Moussa Fall',
            'customer_phone' => '+221 76 444 55 66',
            'customer_address' => 'Dakar, Yoff',
            'subtotal' => 4000,
            'delivery_fee' => 1000,
            'total' => 5000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'confirmed',
        ]);

        $this->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/orders')
            ->assertUnauthorized();

        $customer = User::factory()->create();
        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/orders')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/orders?status=pending&search=Aminata&page=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matchingOrder->id);

        $this->getJson('/api/admin/orders?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->getJson('/api/admin/orders?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page']);
    }

    public function test_admin_can_confirm_order_and_reduce_stock_once(): void
    {
        [$admin, $product] = $this->prepareScenario();

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Alice Client',
            'customer_phone' => '+221 77 001 02 03',
            'customer_address' => 'Dakar, Médina, Rue 12',
            'subtotal' => 2000,
            'delivery_fee' => 1000,
            'total' => 3000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 2000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertSame(3, Product::find($product->id)->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'type' => 'order_confirmation',
            'quantity_change' => -2,
            'stock_before' => 5,
            'stock_after' => 3,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(3, Product::find($product->id)->stock);
        $this->assertSame(
            1,
            InventoryMovement::where('order_id', $order->id)->where('type', 'order_confirmation')->count(),
        );
    }

    public function test_admin_cannot_confirm_order_when_stock_is_insufficient(): void
    {
        [$admin, $product] = $this->prepareScenario(1);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Bob Client',
            'customer_phone' => '+221 77 010 11 12',
            'customer_address' => 'Dakar, Grand Yoff, Rue 27',
            'subtotal' => 5000,
            'delivery_fee' => 1000,
            'total' => 6000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 5000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Stock insuffisant pour Portable Test : 1 disponible(s), 2 requis.');

        $this->assertSame(1, Product::find($product->id)->stock);
    }

    public function test_confirmed_order_cancelled_re_stocks_items_once(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Claude Client',
            'customer_phone' => '+221 77 020 21 22',
            'customer_address' => 'Dakar, Plateau, Avenue 45',
            'subtotal' => 4000,
            'delivery_fee' => 2000,
            'total' => 6000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 4000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(3, Product::find($product->id)->stock);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, Product::find($product->id)->stock);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'order_cancellation',
            'quantity_change' => 2,
            'stock_before' => 3,
            'stock_after' => 5,
        ]);
    }

    public function test_delivered_order_cannot_restore_stock_when_cancelled(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Diane Client',
            'customer_phone' => '+221 77 030 31 32',
            'customer_address' => 'Dakar, Parcelles Assainies, Rue 88',
            'subtotal' => 3000,
            'delivery_fee' => 1000,
            'total' => 4000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 3000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'preparing'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'out_for_delivery'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'delivered'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Cette commande est terminée : son statut ne peut plus changer.');

        $this->assertSame(3, Product::find($product->id)->stock);
    }

    public function test_invalid_status_transitions_do_not_change_stock_or_order_status(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Ibrahima Client',
            'customer_phone' => '+221 77 123 45 67',
            'customer_address' => 'Dakar, Fann, Rue 10',
            'subtotal' => 2000,
            'delivery_fee' => 1000,
            'total' => 3000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 2000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'preparing'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Transition impossible : une commande « pending » ne peut pas passer au statut « preparing ».');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(5, Product::find($product->id)->stock);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Transition impossible : une commande « confirmed » ne peut pas passer au statut « pending ».');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(3, Product::find($product->id)->stock);
    }

    public function test_cancelling_a_preparing_order_restores_stock_once(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Awa Client',
            'customer_phone' => '+221 76 123 45 67',
            'customer_address' => 'Dakar, Mermoz, Rue 5',
            'subtotal' => 2000,
            'delivery_fee' => 1000,
            'total' => 3000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 2000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'preparing'])
            ->assertOk();

        $this->assertSame(3, Product::find($product->id)->stock);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, Product::find($product->id)->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_manual_stock_adjustments_are_audited_and_noop_updates_create_no_movement(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/products/'.$product->id, ['stock' => 8])
            ->assertOk()
            ->assertJsonPath('stock', 8);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => 'manual_adjustment',
            'quantity_change' => 3,
            'stock_before' => 5,
            'stock_after' => 8,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/products/'.$product->id, ['stock' => 8])
            ->assertOk();

        $this->assertSame(1, InventoryMovement::where('product_id', $product->id)->count());
    }

    public function test_stock_movements_are_visible_to_admin(): void
    {
        [$admin, $product] = $this->prepareScenario(5);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/products/'.$product->id.'/stock-movements')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/products/'.$product->id, ['stock' => 8])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/products/'.$product->id.'/stock-movements')
            ->assertOk()
            ->assertJsonPath('data.0.product_name', 'Portable Test')
            ->assertJsonPath('data.0.user.id', $admin->id);
    }

    public function test_initial_stock_is_recorded_when_admin_creates_product(): void
    {
        [$admin] = $this->prepareScenario(5);
        $category = Category::first();

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/products', [
                'category_id' => $category->id,
                'name' => 'Clavier Dakar Test',
                'reference' => 'CLAV-'.uniqid(),
                'brand' => 'Test',
                'description' => null,
                'price' => 12000,
                'stock' => 7,
                'stock_alert_threshold' => 2,
                'processor' => null,
                'ram' => null,
                'storage' => null,
                'graphics_card' => null,
                'screen' => null,
                'state' => 'neuf',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('stock', 7);

        $productId = $response->json('id');
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $productId,
            'user_id' => $admin->id,
            'type' => 'opening_balance',
            'quantity_change' => 7,
            'stock_before' => 0,
            'stock_after' => 7,
        ]);
    }

    public function test_admin_confirmation_sends_email_once_and_marks_status(): void
    {
        Queue::fake();
        Notification::fake();
        [$admin, $product] = $this->prepareScenario(10);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Evelyne Client',
            'customer_phone' => '+221 77 040 41 42',
            'customer_address' => 'Dakar, Fann, Rue 31',
            'subtotal' => 6000,
            'delivery_fee' => 1000,
            'total' => 7000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 6000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job) =>
            $job->orderId === $order->id && $job->channel === 'email'
        );
        $this->assertSame('pending', $order->fresh()->email_status);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();
        Queue::assertPushedTimes(SendOrderNotification::class, 1);

        app(OrderNotificationService::class)->send($order->id, 'email');
        Notification::assertSentTo($admin, OrderConfirmedNotification::class, function (OrderConfirmedNotification $notification, array $channels) use ($order) {
            return $notification->order->id === $order->id && in_array('mail', $channels, true);
        });

        $this->assertSame('sent', $order->fresh()->email_status);
    }

    public function test_failed_confirmation_email_keeps_order_confirmed_and_allows_retry(): void
    {
        Queue::fake();
        Notification::fake();

        [$admin, $product] = $this->prepareScenario(10);

        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Fabrice Client',
            'customer_phone' => '+221 77 050 51 52',
            'customer_address' => 'Dakar, Mermoz, Rue 55',
            'subtotal' => 6000,
            'delivery_fee' => 1000,
            'total' => 7000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 6000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        try {
            app(OrderNotificationService::class)->send($order->id, 'email');
        } catch (\RuntimeException) {
        }

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->email_status);
        $this->assertSame(1, $order->fresh()->email_attempts);

        Notification::fake();
        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/admin/orders/'.$order->id.'/notifications/retry', ['channel' => 'email'])
            ->assertOk();

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job) =>
            $job->orderId === $order->id && $job->channel === 'email'
        );
        app(OrderNotificationService::class)->send($order->id, 'email');
        Notification::assertSentTo($admin, OrderConfirmedNotification::class, function (OrderConfirmedNotification $notification, array $channels) use ($order) {
            return $notification->order->id === $order->id && in_array('mail', $channels, true);
        });

        $this->assertSame('sent', $order->fresh()->email_status);
    }

    public function test_configured_whatsapp_template_is_sent_once_on_confirmation(): void
    {
        Queue::fake();
        Notification::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.template_name' => 'order_confirmation',
            'services.whatsapp.template_language' => 'fr',
        ]);

        [$admin, $product] = $this->prepareScenario(10);
        $order = Order::create([
            'user_id' => $admin->id,
            'delivery_zone_id' => DeliveryZone::first()->id,
            'customer_name' => 'Grace Client',
            'customer_phone' => '+221 77 708 09 10',
            'customer_address' => 'Dakar, Ouakam, Rue 19',
            'subtotal' => 2000,
            'delivery_fee' => 1000,
            'total' => 3000,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
            'whatsapp_consent' => true,
        ]);
        $order->update(['number' => 'GT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'total' => 2000,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('whatsapp_status', 'pending');

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job) =>
            $job->orderId === $order->id && $job->channel === 'whatsapp'
        );
        app(OrderNotificationService::class)->send($order->id, 'whatsapp');

        Http::assertSent(function (HttpRequest $request) use ($order): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/test-phone-number-id/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '221777080910'
                && $request['template']['name'] === 'order_confirmation'
                && $request['template']['language']['code'] === 'fr'
                && $request['template']['components'][0]['parameters'][1]['text'] === 'GT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
        });

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(8, Product::find($product->id)->stock);
        $this->assertSame('sent', $order->fresh()->whatsapp_status);
    }

    public function test_whatsapp_is_not_queued_without_explicit_customer_consent(): void
    {
        Queue::fake();
        [$admin] = $this->prepareScenario();
        $order = Order::create([
            'user_id' => $admin->id,
            'customer_name' => 'Client without consent',
            'customer_phone' => '+221 77 040 41 42',
            'customer_address' => 'Dakar, Fann, Rue 31',
            'subtotal' => 1000,
            'delivery_fee' => 500,
            'total' => 1500,
            'payment_method' => 'cash_on_delivery',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('whatsapp_status', 'skipped');

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job) =>
            $job->orderId === $order->id && $job->channel === 'email'
        );
        Queue::assertNotPushed(SendOrderNotification::class, fn (SendOrderNotification $job) =>
            $job->orderId === $order->id && $job->channel === 'whatsapp'
        );
    }

    private function prepareScenario(int $stock = 5): array
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin'.uniqid().'@example.com',
            'phone' => '+221 770000000',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Ordinateurs',
            'slug' => 'ordinateurs-'.uniqid(),
            'description' => 'Catégorie test',
            'is_active' => true,
        ]);

        DeliveryZone::create([
            'name' => 'Dakar Centre',
            'fee' => 1000,
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Portable Test',
            'slug' => 'portable-test-'.uniqid(),
            'reference' => 'PT-'.uniqid(),
            'brand' => 'Dell',
            'description' => 'Produit pour test de stock',
            'price' => 1000,
            'stock' => $stock,
            'stock_alert_threshold' => 2,
            'state' => 'neuf',
            'is_active' => true,
        ]);

        return [$admin, $product];
    }
}
