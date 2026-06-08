<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('vision_profile')->nullable()->after('tags_ia');
            $table->timestamp('vision_profile_at')->nullable()->after('vision_profile');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('color_profile')->nullable()->after('sizes_stock');
            $table->timestamp('color_profile_at')->nullable()->after('color_profile');
            $table->json('image_embedding')->nullable()->after('color_profile_at');
            $table->timestamp('embedding_at')->nullable()->after('image_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['color_profile', 'color_profile_at', 'image_embedding', 'embedding_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['vision_profile', 'vision_profile_at']);
        });
    }
};
