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
        Schema::create('prompts_maestros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Prompt Maestro Principal');
            $table->text('instrucciones_sistema'); // Cómo debe comportarse la IA
            $table->text('reglas_negocio')->nullable(); // Reglas específicas de venta
            $table->text('ejemplos_respuestas')->nullable(); // Few-shot examples
            $table->text('restricciones')->nullable(); // Lo que NO debe hacer
            $table->boolean('activo')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Quién lo creó/editó
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompts_maestros');
    }
};
