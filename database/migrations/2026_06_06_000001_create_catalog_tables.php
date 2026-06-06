<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('');
            $table->string('yape_number')->default('');
            $table->string('yape_name')->default('');
            $table->json('business_hours')->nullable();
            $table->json('social_networks')->nullable();
            $table->string('address')->nullable();
            $table->string('sales_tone')->nullable();
            $table->string('sales_closing_cta')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discount', 8, 2)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('disponible');
            $table->json('tags_ia')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('color');
            $table->string('image_path')->nullable();
            $table->string('image_url')->nullable();
            $table->json('sizes_stock')->nullable();
            $table->timestamps();
        });

        Schema::create('producto_similares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('similar_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'similar_product_id']);
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('district');
            $table->decimal('cost_motorizado', 8, 2);
            $table->decimal('cost_shalom', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('producto_similares');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('categories');
    }
};
