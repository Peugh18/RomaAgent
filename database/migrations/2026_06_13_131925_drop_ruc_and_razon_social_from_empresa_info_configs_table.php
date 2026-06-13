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
        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->dropColumn(['ruc', 'razon_social']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa_info_configs', function (Blueprint $table) {
            $table->string('ruc', 20)->nullable();
            $table->string('razon_social')->nullable();
        });
    }
};
