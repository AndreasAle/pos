<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint');
            $table->string('type', 50);
            $table->string('severity', 20)->default('info');
            $table->string('title');
            $table->text('summary');
            $table->json('data')->nullable();
            $table->string('action_label')->nullable();
            $table->string('action_route')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'fingerprint']);
            $table->index(['business_id', 'dismissed_at', 'expires_at']);
        });

        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'user_id', 'updated_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->string('tool_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
        Schema::dropIfExists('business_insights');
    }
};
