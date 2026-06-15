<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->string('vendedor_nombre')->nullable()->after('company_name');
            $table->string('vendedor_genero')->nullable()->after('vendedor_nombre');
            $table->text('descripcion_empresa')->nullable()->after('website');
        });

        Schema::table('agente_configs', function (Blueprint $table) {
            $table->text('estilo_ventas')->nullable()->after('personalidad_bot');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->dropColumn(['vendedor_nombre', 'vendedor_genero', 'descripcion_empresa']);
        });

        Schema::table('agente_configs', function (Blueprint $table) {
            $table->dropColumn(['estilo_ventas']);
        });
    }
};
