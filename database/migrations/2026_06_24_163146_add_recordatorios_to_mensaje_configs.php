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
        Schema::table('mensaje_configs', function (Blueprint $table) {
            $table->text('recordatorio_motorizado')->nullable()->after('pedido_entregado');
            $table->text('recordatorio_shalom')->nullable()->after('recordatorio_motorizado');
        });
    }

    public function down(): void
    {
        Schema::table('mensaje_configs', function (Blueprint $table) {
            $table->dropColumn(['recordatorio_motorizado', 'recordatorio_shalom']);
        });
    }
};
