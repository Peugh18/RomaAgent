<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla para feedback de aprendizaje
        Schema::create('vision_learning_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('variant_id_correcto')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('tipo_feedback', 20)->default('pendiente');
            $table->json('contexto_analisis');
            $table->decimal('peso_aprendizaje', 3, 2)->default(1.0);

            $table->timestamps();

            $table->index(['tipo_feedback', 'created_at']);
            $table->index('variant_id');
            $table->index('created_at');
        });

        // Agregar campos a product_variants para aprendizaje
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('vision_confidence', 3, 2)->default(0.70)->after('embedding_at');
            $table->integer('vision_popularity')->default(0)->after('vision_confidence');
            $table->timestamp('vision_boosted_at')->nullable()->after('vision_popularity');

            $table->index('vision_popularity');
            $table->index('vision_confidence');
        });

        // Tabla para caché de análisis (opcional, para optimizar)
        Schema::create('vision_analysis_cache', function (Blueprint $table) {
            $table->id();
            $table->string('image_hash', 64)->unique(); // hash de la imagen
            $table->json('analysis_result');
            $table->json('catalog_match_result')->nullable();
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index('last_hit_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vision_analysis_cache');
        Schema::dropIfExists('vision_learning_feedback');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['vision_confidence', 'vision_popularity', 'vision_boosted_at']);
            $table->dropIndex(['vision_popularity', 'vision_confidence']);
        });
    }
};
