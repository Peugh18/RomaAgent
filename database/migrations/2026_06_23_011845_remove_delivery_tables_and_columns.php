<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop delivery-specific tables entirely
        Schema::dropIfExists('delivery_method_fields');
        Schema::dropIfExists('delivery_methods');
        Schema::dropIfExists('delivery_zones');

        // Remove delivery columns from sales table
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['delivery_cost', 'delivery_type', 'delivery_district']);
        });
    }

    public function down(): void
    {
        // Restore delivery_zones
        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('district');
            $table->string('zone')->nullable();
            $table->decimal('motorizado_price', 8, 2)->nullable();
            $table->decimal('shalom_price', 8, 2)->nullable();
            $table->string('horario_motorizado')->nullable();
            $table->string('horario_shalom')->nullable();
            $table->timestamps();
        });

        // Restore delivery_methods
        Schema::create('delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Restore delivery_method_fields
        Schema::create('delivery_method_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_method_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Restore columns in sales
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('delivery_cost', 8, 2)->default(0)->after('unit_price');
            $table->string('delivery_type')->nullable()->after('payment_method');
            $table->string('delivery_district')->nullable()->after('delivery_type');
        });
    }
};
