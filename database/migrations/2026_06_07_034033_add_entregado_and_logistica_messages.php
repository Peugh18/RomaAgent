<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('mensaje_pedido_enviado')->nullable()->after('mensaje_pedido_confirmado');
            $table->text('mensaje_pedido_entregado')->nullable()->after('mensaje_pedido_enviado');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['mensaje_pedido_enviado', 'mensaje_pedido_entregado']);
        });
    }
};
