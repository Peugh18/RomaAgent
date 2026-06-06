<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Elimina campos duplicados que ya existen en otras tablas o son redundantes.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Eliminar datos de Yape duplicados (usar metodos_pago array)
            $table->dropColumn([
                'titular_yape',
                'numero_yape',
            ]);

            // Eliminar tarifario duplicado (usar delivery_zones tabla)
            $table->dropColumn([
                'tarifario_motorizado',
                'tarifa_shalom_lima',
                'tarifa_shalom_provincia',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('titular_yape')->nullable()->after('numero_yape');
            $table->string('numero_yape')->nullable()->after('mensaje_recordatorio_15min');
            $table->json('tarifario_motorizado')->nullable()->after('comision_tarjeta');
            $table->decimal('tarifa_shalom_lima', 5, 2)->nullable()->after('tarifario_motorizado');
            $table->decimal('tarifa_shalom_provincia', 5, 2)->nullable()->after('tarifa_shalom_lima');
        });
    }
};
