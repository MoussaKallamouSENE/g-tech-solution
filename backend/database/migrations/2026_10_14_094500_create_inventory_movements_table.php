<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_reference');
            $table->string('type');
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['order_id', 'type']);
        });

        DB::table('products')
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunk(500, function ($products): void {
                $timestamp = now();
                $movements = $products->map(fn ($product): array => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_reference' => $product->reference,
                    'type' => 'opening_balance',
                    'quantity_change' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                    'note' => 'Solde initial lors de la mise en place du suivi des mouvements.',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                DB::table('inventory_movements')->insert($movements);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
