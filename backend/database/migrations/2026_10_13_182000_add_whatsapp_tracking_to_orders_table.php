<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('whatsapp_status')->default('not_configured')->after('email_sent_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_status', 'whatsapp_sent_at']);
        });
    }
};
