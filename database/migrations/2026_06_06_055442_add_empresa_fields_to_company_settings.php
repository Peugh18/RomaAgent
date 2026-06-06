<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Datos de empresa
            $table->string('ruc', 20)->nullable()->after('company_name');
            $table->string('razon_social')->nullable()->after('ruc');
            $table->string('celular', 20)->nullable()->after('razon_social');
            $table->string('email')->nullable()->change(); // Ya existe, solo cambiar nullable
            $table->string('website')->nullable()->after('email');
            $table->string('logo_path')->nullable()->after('website');

            // Actividad económica
            $table->string('actividad_economica')->nullable()->after('logo_path');

            // Personalidad del bot
            $table->string('tono_bot')->default('cálido y cercano')->after('actividad_economica');
            $table->string('estilo_comunicacion')->default('natural')->after('tono_bot');

            // Moneda
            $table->string('moneda', 3)->default('PEN')->after('estilo_comunicacion');

            // Métodos de pago (JSON)
            $table->json('metodos_pago')->nullable()->after('moneda');

            // Información extra
            $table->text('horario_atencion')->nullable()->after('metodos_pago');
            $table->text('politica_devoluciones')->nullable()->after('horario_atencion');
            $table->text('restricciones_especiales')->nullable()->after('politica_devoluciones');
            $table->text('informacion_adicional')->nullable()->after('restricciones_especiales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ruc',
                'razon_social',
                'celular',
                'website',
                'logo_path',
                'actividad_economica',
                'tono_bot',
                'estilo_comunicacion',
                'moneda',
                'metodos_pago',
                'horario_atencion',
                'politica_devoluciones',
                'restricciones_especiales',
                'informacion_adicional',
            ]);
        });
    }
};
