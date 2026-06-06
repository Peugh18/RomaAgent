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
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('company_name')->nullable()->default(null)->change();
            $table->string('yape_number')->nullable()->default(null)->change();
            $table->string('yape_name')->nullable()->default(null)->change();
            $table->string('sales_tone')->nullable()->default(null)->change();
            $table->string('sales_closing_cta')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('company_name')->default('')->nullable(false)->change();
            $table->string('yape_number')->default('')->nullable(false)->change();
            $table->string('yape_name')->default('')->nullable(false)->change();
            $table->string('sales_tone')->nullable(false)->change();
            $table->string('sales_closing_cta')->nullable(false)->change();
        });
    }
};
