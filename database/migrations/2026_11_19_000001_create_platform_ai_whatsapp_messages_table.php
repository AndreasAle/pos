<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 8); // inbound | outbound
            $table->string('phone', 20)->index();
            $table->string('name', 120)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->foreignId('platform_ai_task_id')->nullable()->constrained('platform_ai_tasks', 'id', 'ai_wa_msg_task_fk')->nullOnDelete();
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_whatsapp_messages');
    }
};
