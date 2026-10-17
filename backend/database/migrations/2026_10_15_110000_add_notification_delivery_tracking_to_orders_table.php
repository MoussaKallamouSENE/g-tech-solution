<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('whatsapp_consent')->default(false)->after('customer_phone');
            $table->unsignedSmallInteger('email_attempts')->default(0)->after('email_sent_at');
            $table->text('email_error')->nullable()->after('email_attempts');
            $table->unsignedSmallInteger('whatsapp_attempts')->default(0)->after('whatsapp_sent_at');
            $table->text('whatsapp_error')->nullable()->after('whatsapp_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_consent',
                'email_attempts',
                'email_error',
                'whatsapp_attempts',
                'whatsapp_error',
            ]);
        });
    }
};
