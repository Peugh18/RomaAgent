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
        Schema::create('logs_ia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->index(); // 'request', 'response', 'error'
            $table->string('phone_number', 20)->nullable()->index();
            $table->string('modelo', 50)->nullable();
            $table->text('prompt')->nullable(); // Prompt enviado (truncado)
            $table->text('respuesta')->nullable(); // Respuesta recibida (truncada)
            $table->integer('tokens_entrada')->nullable();
            $table->integer('tokens_salida')->nullable();
            $table->integer('http_status')->nullable(); // Status code de la API
            $table->text('error_mensaje')->nullable(); // Mensaje de error si falló
            $table->string('error_codigo', 100)->nullable()->index(); // Código de error
            $table->decimal('tiempo_respuesta_ms', 8, 2)->nullable(); // Tiempo en milisegundos
            $table->timestamps();

            $table->index(['tipo', 'created_at']);
            $table->index(['error_codigo', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_ia');
    }
};
