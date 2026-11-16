<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 180);
            $table->string('mime', 120);
            $table->string('kind', 20);
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
            $table->index(['platform_ai_agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_attachments');
    }
};
