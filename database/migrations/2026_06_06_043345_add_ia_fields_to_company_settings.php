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
            $table->boolean('agente_ia_activado')->default(false)->after('sales_closing_cta');
            $table->string('agente_ia_modelo')->default('gemini-2.5-pro')->after('agente_ia_activado');
            $table->text('agente_ia_api_key_encrypted')->nullable()->after('agente_ia_modelo');
            $table->decimal('agente_ia_temperatura', 3, 2)->default(0.7)->after('agente_ia_api_key_encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'agente_ia_activado',
                'agente_ia_modelo',
                'agente_ia_api_key_encrypted',
                'agente_ia_temperatura',
            ]);
        });
    }
};
