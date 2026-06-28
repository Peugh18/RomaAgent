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
        Schema::create('visual_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('image_hash', 32)->nullable()->index(); // Hash MD5 para emparejamientos instantáneos
            $table->text('huella_forma')->nullable(); // Descripción geométrica
            $table->json('image_embedding')->nullable(); // Vector matemático descriptivo
            $table->foreignId('product_id')->constrained()->onDelete('cascade'); // El producto correcto
            $table->foreignId('original_product_id')->nullable()->constrained('products')->onDelete('set null'); // El producto equivocado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visual_corrections');
    }
};
