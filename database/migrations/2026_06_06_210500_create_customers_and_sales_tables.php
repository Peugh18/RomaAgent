<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('name')->nullable();
            $table->boolean('ia_paused')->default(false);
            $table->string('ia_pause_reason')->nullable();
            $table->foreignId('active_sale_id')->nullable();
            $table->timestamps();

            $table->index('ia_paused');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('color')->nullable();
            $table->string('size')->default('UNICA');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->string('delivery_type')->nullable();
            $table->string('delivery_district')->nullable();
            $table->string('status', 32);
            $table->json('customer_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('payment_received_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['phone_number', 'status']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('active_sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['active_sale_id']);
        });

        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
