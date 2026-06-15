<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE vision_learning_feedback MODIFY tipo_feedback VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE vision_learning_feedback MODIFY tipo_feedback ENUM('positivo', 'negativo') NOT NULL");
    }
};
