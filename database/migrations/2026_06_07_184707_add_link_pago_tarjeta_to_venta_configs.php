<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_configs', function (Blueprint $table) {
            $table->string('link_pago_tarjeta', 2048)->nullable()->after('comision_tarjeta');
        });
    }

    public function down(): void
    {
        Schema::table('venta_configs', function (Blueprint $table) {
            $table->dropColumn('link_pago_tarjeta');
        });
    }
};
