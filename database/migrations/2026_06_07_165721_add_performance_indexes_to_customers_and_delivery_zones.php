<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('last_inbound_at');
            $table->index(['active_sale_id', 'ia_paused', 'last_inbound_at'], 'customers_reminders_index');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->unique('district');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['last_inbound_at']);
            $table->dropIndex('customers_reminders_index');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropUnique(['district']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
