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
        Schema::create('zonas_envio', function (Blueprint $table) {
            $table->id();
            $table->string('departamento');
            $table->string('provincia');
            $table->string('distrito')->index();
            $table->string('tipo_envio')->index(); // 'motorizado', 'shalom', 'olva', etc.
            $table->decimal('costo_referencial', 8, 2);
            $table->boolean('activo')->default(true);
            $table->string('observaciones')->nullable();
            $table->json('datos_requeridos')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zonas_envio');
    }
};
