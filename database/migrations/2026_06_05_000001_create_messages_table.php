<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('phone_number');
            $table->string('customer_name')->nullable();
            $table->text('content');
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->timestamp('whatsapp_timestamp')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('phone_number');
            $table->index(['phone_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
