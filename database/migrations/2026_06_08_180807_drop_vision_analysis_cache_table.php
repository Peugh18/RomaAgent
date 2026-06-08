<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vision_analysis_cache');
    }

    public function down(): void
    {
        Schema::create('vision_analysis_cache', function (Blueprint $table) {
            $table->id();
            $table->string('image_hash', 64)->unique();
            $table->json('analysis_result');
            $table->json('catalog_match_result')->nullable();
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index('last_hit_at');
        });
    }
};
