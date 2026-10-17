<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_expected_metrics(): void
    {
        $admin = User::create([
            'name' => 'Admin Dashboard Test',
            'email' => 'admin-dashboard-test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'sales_by_day',
            'previous_week_total',
            'orders_total',
            'orders_pending',
            'repairs_new',
            'deliveries_in_delivery',
            'gps_active',
            'low_stock',
            'stock_units_available',
            'purchases_month_total',
            'purchases_month_count',
            'top_products',
            'recent_orders',
            'low_stock_products',
            'recent_purchases',
        ]);
    }

    public function test_admin_dashboard_requires_admin_role(): void
    {
        $customer = User::create([
            'name' => 'Customer Test',
            'email' => 'customer-dashboard-test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }
}
