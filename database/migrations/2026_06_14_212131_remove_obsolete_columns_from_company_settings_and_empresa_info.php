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
            $table->dropColumn([
                'celular',
                'email',
                'website',
                'informacion_adicional',
                'flujo_ventas',
                'formato_registro_venta',
            ]);
        });

        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->dropColumn([
                'celular',
                'email',
                'website',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('informacion_adicional')->nullable();
            $table->text('flujo_ventas')->nullable();
            $table->text('formato_registro_venta')->nullable();
        });

        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
        });
    }
};
