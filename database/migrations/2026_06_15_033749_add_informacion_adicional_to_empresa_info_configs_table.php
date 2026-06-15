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
        if (! Schema::hasColumn('empresa_info_configs', 'informacion_adicional')) {
            Schema::table('empresa_info_configs', function (Blueprint $table) {
                $table->text('informacion_adicional')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('empresa_info_configs', 'informacion_adicional')) {
            Schema::table('empresa_info_configs', function (Blueprint $table) {
                $table->dropColumn('informacion_adicional');
            });
        }
    }
};
