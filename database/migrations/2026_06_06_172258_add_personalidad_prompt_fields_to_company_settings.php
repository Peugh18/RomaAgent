<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('personalidad_bot')->nullable()->after('estilo_comunicacion');
            $table->text('respuesta_si_es_bot')->nullable()->after('personalidad_bot');
            $table->text('mensaje_recordatorio_datos')->nullable()->after('mensaje_recordatorio_15min');
            $table->text('formato_registro_venta')->nullable()->after('protocolo_traspaso');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'personalidad_bot',
                'respuesta_si_es_bot',
                'mensaje_recordatorio_datos',
                'formato_registro_venta',
            ]);
        });
    }
};
