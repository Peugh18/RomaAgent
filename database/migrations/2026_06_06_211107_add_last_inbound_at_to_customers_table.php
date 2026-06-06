<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('last_inbound_at')->nullable()->after('active_sale_id');
            $table->timestamp('reminder_3min_sent_at')->nullable()->after('last_inbound_at');
            $table->timestamp('reminder_15min_sent_at')->nullable()->after('reminder_3min_sent_at');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->json('agent_metadata')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('agent_metadata');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['last_inbound_at', 'reminder_3min_sent_at', 'reminder_15min_sent_at']);
        });
    }
};
