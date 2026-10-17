<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_assign_drivers_and_drivers_only_see_their_own_deliveries(): void
    {
        [$admin, $driver, $otherDriver, $customer, $order] = $this->scenario();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/orders/'.$order->id.'/delivery', ['driver_id' => $driver->id])
            ->assertCreated()
            ->assertJsonPath('status', 'to_prepare')
            ->assertJsonPath('driver.id', $driver->id);

        $this->actingAs($otherDriver, 'sanctum')
            ->getJson('/api/driver/deliveries')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($driver, 'sanctum')
            ->getJson('/api/driver/deliveries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order.number', $order->number);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/deliveries')
            ->assertForbidden();

        $this->actingAs($otherDriver, 'sanctum')
            ->postJson('/api/driver/deliveries/1/location/start')
            ->assertNotFound();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/admin/orders/'.$order->id.'/delivery', ['driver_id' => $driver->id])
            ->assertForbidden();
    }

    public function test_admin_can_only_see_gps_while_assigned_driver_has_sharing_active(): void
    {
        [$admin, $driver, , $customer, $order] = $this->scenario();
        $delivery = $this->assignAndStart($admin, $driver, $order);

        $this->actingAs($driver, 'sanctum')
            ->putJson('/api/driver/deliveries/'.$delivery->id.'/location', [
                'latitude' => 14.6928,
                'longitude' => -17.4467,
                'accuracy' => 18,
            ])
            ->assertOk()
            ->assertJsonPath('location_available', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.latitude', 14.6928)
            ->assertJsonPath('data.0.longitude', -17.4467);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonMissingPath('delivery.latitude');

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/driver/deliveries/'.$delivery->id.'/location/stop')
            ->assertOk()
            ->assertJsonPath('location_available', false)
            ->assertJsonPath('latitude', null);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.latitude', null);
    }

    public function test_customer_can_only_access_tracking_for_their_own_orders(): void
    {
        [, $driver, , $customer, $order] = $this->scenario();
        $otherCustomer = User::factory()->create(['role' => 'customer']);

        $this->getJson('/api/orders/'.$order->id.'/delivery')
            ->assertUnauthorized();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/delivery')
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.status', 'confirmed')
            ->assertJsonPath('delivery', null);

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/delivery')
            ->assertNotFound();

        $this->actingAs($driver, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/delivery')
            ->assertForbidden();
    }

    public function test_customer_tracking_exposes_only_active_shared_position_and_reports_stale_location(): void
    {
        [$admin, $driver, , $customer, $order] = $this->scenario();
        $delivery = $this->assignAndStart($admin, $driver, $order);
        $url = '/api/orders/'.$order->id.'/delivery';

        $this->actingAs($customer, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('delivery.status', 'in_delivery')
            ->assertJsonPath('delivery.driver.name', $driver->name)
            ->assertJsonPath('delivery.location_sharing_active', true)
            ->assertJsonPath('delivery.location_available', false)
            ->assertJsonPath('delivery.latitude', null)
            ->assertJsonMissingPath('delivery.failure_reason')
            ->assertJsonMissingPath('delivery.order.customer_phone');

        $this->actingAs($driver, 'sanctum')
            ->putJson('/api/driver/deliveries/'.$delivery->id.'/location', [
                'latitude' => 14.6928,
                'longitude' => -17.4467,
                'accuracy' => 18,
            ])
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('delivery.location_available', true)
            ->assertJsonPath('delivery.latitude', 14.6928)
            ->assertJsonPath('delivery.longitude', -17.4467);

        $delivery->forceFill(['location_updated_at' => now()->subMinutes(7)])->save();
        $this->actingAs($customer, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('delivery.location_is_stale', true)
            ->assertJsonPath('delivery.location_available', true);

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/driver/deliveries/'.$delivery->id.'/location/stop')
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('delivery.location_sharing_active', false)
            ->assertJsonPath('delivery.location_available', false)
            ->assertJsonPath('delivery.latitude', null)
            ->assertJsonPath('delivery.location_updated_at', null);

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/driver/deliveries/'.$delivery->id.'/location/start')
            ->assertOk();
        $this->actingAs($driver, 'sanctum')
            ->patchJson('/api/driver/deliveries/'.$delivery->id.'/status', [
                'status' => 'delivered',
                'delivery_confirmed' => true,
            ])
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('delivery.status', 'delivered')
            ->assertJsonPath('delivery.location_sharing_active', false)
            ->assertJsonPath('delivery.location_available', false)
            ->assertJsonPath('delivery.latitude', null);
    }

    public function test_gps_coordinates_and_delivery_status_transitions_are_validated(): void
    {
        [$admin, $driver, , , $order] = $this->scenario();
        $delivery = $this->assignAndStart($admin, $driver, $order);

        $this->actingAs($driver, 'sanctum')
            ->putJson('/api/driver/deliveries/'.$delivery->id.'/location', [
                'latitude' => 91,
                'longitude' => -17.4467,
            ])
            ->assertUnprocessable();

        $this->actingAs($driver, 'sanctum')
            ->patchJson('/api/driver/deliveries/'.$delivery->id.'/status', ['status' => 'delivered'])
            ->assertUnprocessable();

        $this->actingAs($driver, 'sanctum')
            ->patchJson('/api/driver/deliveries/'.$delivery->id.'/status', [
                'status' => 'delivered',
                'delivery_confirmed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'delivered')
            ->assertJsonPath('location_sharing_active', false);

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertDatabaseHas('delivery_status_histories', [
            'delivery_id' => $delivery->id,
            'status' => 'delivered',
            'changed_by' => $driver->id,
        ]);
    }

    public function test_existing_admin_order_status_flow_keeps_an_assigned_delivery_in_sync(): void
    {
        [$admin, $driver, , , $order] = $this->scenario();
        $assignment = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/orders/'.$order->id.'/delivery', ['driver_id' => $driver->id])
            ->assertCreated();
        $deliveryId = $assignment->json('id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'preparing'])
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/orders/'.$order->id.'/status', ['status' => 'out_for_delivery'])
            ->assertOk();

        $this->assertDatabaseHas('deliveries', [
            'id' => $deliveryId,
            'status' => 'ready_for_delivery',
        ]);
        $this->assertDatabaseHas('delivery_status_histories', [
            'delivery_id' => $deliveryId,
            'status' => 'ready_for_delivery',
        ]);

        $this->actingAs($driver, 'sanctum')
            ->patchJson('/api/driver/deliveries/'.$deliveryId.'/status', ['status' => 'in_delivery'])
            ->assertOk();
        $this->assertSame('out_for_delivery', $order->fresh()->status);
    }

    public function test_public_registration_cannot_assign_an_admin_or_driver_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Public user',
            'email' => 'public'.uniqid().'@example.com',
            'phone' => '+221 770000000',
            'password' => 'a-secure-password',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertSame('customer', User::find($response->json('user.id'))->role);
    }

    public function test_only_admin_can_create_authorized_driver_accounts(): void
    {
        [$admin, , , $customer, $order] = $this->scenario();
        $payload = [
            'name' => 'New Driver',
            'email' => 'new-driver'.uniqid().'@example.com',
            'phone' => '+221 77 000 00 01',
            'password' => 'a-very-secure-password',
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/admin/drivers', $payload)
            ->assertForbidden();

        $createdDriver = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/drivers', $payload)
            ->assertCreated()
            ->assertJsonPath('role', 'driver');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/orders/'.$order->id.'/delivery', ['driver_id' => $createdDriver->json('id')])
            ->assertCreated()
            ->assertJsonPath('driver.id', $createdDriver->json('id'));

        $login = $this->postJson('/api/login', [
            'email' => $payload['email'],
            'password' => $payload['password'],
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'driver');

        Auth::forgetGuards();
        $this->withToken($login->json('token'))
            ->getJson('/api/driver/deliveries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.driver.id', $createdDriver->json('id'))
            ->assertJsonPath('data.0.status', 'to_prepare');
    }

    private function scenario(): array
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => uniqid('admin'). '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
        $driver = User::create([
            'name' => 'Driver One',
            'email' => uniqid('driver1'). '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'driver',
        ]);
        $otherDriver = User::create([
            'name' => 'Driver Two',
            'email' => uniqid('driver2'). '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'driver',
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'user_id' => $customer->id,
            'customer_name' => 'Client',
            'customer_phone' => '+221 77 123 45 67',
            'customer_address' => 'Dakar, Plateau, rue 1',
            'subtotal' => 2000,
            'delivery_fee' => 500,
            'total' => 2500,
            'payment_method' => 'cash_on_delivery',
            'status' => 'confirmed',
        ]);
        $order->update(['number' => 'GT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

        return [$admin, $driver, $otherDriver, $customer, $order];
    }

    private function assignAndStart(User $admin, User $driver, Order $order): Delivery
    {
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/orders/'.$order->id.'/delivery', ['driver_id' => $driver->id])
            ->assertCreated();
        $delivery = Delivery::findOrFail($response->json('id'));

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/deliveries/'.$delivery->id.'/status', ['status' => 'ready_for_delivery'])
            ->assertOk();

        $this->actingAs($driver, 'sanctum')
            ->patchJson('/api/driver/deliveries/'.$delivery->id.'/status', ['status' => 'in_delivery'])
            ->assertOk();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/driver/deliveries/'.$delivery->id.'/location/start')
            ->assertOk();

        return $delivery->fresh();
    }
}
