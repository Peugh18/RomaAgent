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
        Schema::table('agente_configs', function (Blueprint $table) {
            $table->text('reglas_venta_criticas')->nullable()->after('respuesta_si_es_bot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agente_configs', function (Blueprint $table) {
            $table->dropColumn('reglas_venta_criticas');
        });
    }
};
