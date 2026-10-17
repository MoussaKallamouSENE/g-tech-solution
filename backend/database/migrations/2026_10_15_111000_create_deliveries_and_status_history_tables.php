<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('to_prepare')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('location_accuracy')->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->boolean('location_sharing_active')->default(false);
            $table->timestamp('location_sharing_started_at')->nullable();
            $table->timestamp('location_sharing_stopped_at')->nullable();
            $table->timestamp('location_expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('delivery_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_histories');
        Schema::dropIfExists('deliveries');
    }
};
