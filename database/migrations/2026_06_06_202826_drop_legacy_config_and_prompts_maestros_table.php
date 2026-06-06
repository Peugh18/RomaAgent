<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prompts_maestros');

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'instrucciones_sistema',
                'reglas_negocio',
                'ejemplos_respuestas',
                'restricciones',
                'yape_number',
                'yape_name',
                'business_hours',
                'sales_tone',
                'sales_closing_cta',
            ]);
        });
    }

    public function down(): void
    {
        Schema::create('prompts_maestros', function (Blueprint $table) {
            $table->id();
            $table->text('instrucciones_sistema');
            $table->text('reglas_negocio')->nullable();
            $table->text('ejemplos_respuestas')->nullable();
            $table->text('restricciones')->nullable();
            $table->timestamps();
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('instrucciones_sistema')->nullable();
            $table->text('reglas_negocio')->nullable();
            $table->text('ejemplos_respuestas')->nullable();
            $table->text('restricciones')->nullable();
            $table->string('yape_number')->nullable();
            $table->string('yape_name')->nullable();
            $table->json('business_hours')->nullable();
            $table->string('sales_tone')->nullable();
            $table->string('sales_closing_cta')->nullable();
        });
    }
};
