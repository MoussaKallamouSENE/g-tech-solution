<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('warranty_conditions')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('product_specs_snapshot')->nullable();
            $table->text('warranty_conditions_snapshot')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 32)->nullable()->unique();
        });

        DB::table('orders')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $year = $order->created_at
                        ? date('Y', strtotime($order->created_at))
                        : date('Y');
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['invoice_number' => sprintf('GTS-FA-%s-%08d', $year, $order->id)]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn('invoice_number');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_specs_snapshot', 'warranty_conditions_snapshot']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('warranty_conditions');
        });
    }
};
