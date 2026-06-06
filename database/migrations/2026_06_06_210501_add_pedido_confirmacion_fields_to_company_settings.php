<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('mensaje_comprobante_recibido')->nullable()->after('formato_registro_venta');
            $table->text('mensaje_comprobante_fuera_horario')->nullable()->after('mensaje_comprobante_recibido');
            $table->text('mensaje_pedido_confirmado')->nullable()->after('mensaje_comprobante_fuera_horario');
            $table->text('mensaje_espera_link_tarjeta')->nullable()->after('mensaje_pedido_confirmado');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mensaje_comprobante_recibido',
                'mensaje_comprobante_fuera_horario',
                'mensaje_pedido_confirmado',
                'mensaje_espera_link_tarjeta',
            ]);
        });
    }
};
