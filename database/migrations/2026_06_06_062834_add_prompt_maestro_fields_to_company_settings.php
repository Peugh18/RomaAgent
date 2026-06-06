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
            $table->text('instrucciones_sistema')->nullable();
            $table->text('reglas_negocio')->nullable();
            $table->text('ejemplos_respuestas')->nullable();
            $table->text('restricciones')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['instrucciones_sistema', 'reglas_negocio', 'ejemplos_respuestas', 'restricciones']);
        });
    }
};
