<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_customers_and_view_order_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Aminata Ndiaye',
            'email' => 'aminata@example.test',
            'phone' => '+221 77 111 22 33',
        ]);
        User::factory()->create([
            'role' => 'customer',
            'name' => 'Moussa Fall',
            'email' => 'moussa@example.test',
        ]);

        $this->createOrder($customer, 'pending', 3000);
        $this->createOrder($customer, 'cancelled', 5000);

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/customers?search=aminata&page=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.spent', 3000);
    }

    public function test_customer_list_requires_admin_access_and_valid_filters(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/customers')
            ->assertUnauthorized();

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/customers')
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/admin/customers?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page']);

        $this->getJson('/api/admin/customers?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    private function createOrder(User $customer, string $status, int $total): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone ?? '+221 77 000 00 00',
            'customer_address' => 'Dakar, Sénégal',
            'subtotal' => $total,
            'delivery_fee' => 0,
            'total' => $total,
            'payment_method' => 'cash_on_delivery',
            'status' => $status,
        ]);
    }
}
