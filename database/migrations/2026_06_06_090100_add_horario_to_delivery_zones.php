<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campo de horario a delivery_zones para centralizar información de entregas.
     */
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->string('horario_entrega')->nullable()->after('cost_shalom')
                ->comment('Horario de entrega para este distrito. Ej: "Lunes a Sábado 5pm-9pm"');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn('horario_entrega');
        });
    }
};
