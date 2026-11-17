<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_owner_contexts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('value');
            $table->boolean('confirmed')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_ai_memories', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_ai_agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('memory_key')->nullable();
            $table->json('content');
            $table->foreignId('source_message_id')->nullable()->constrained('platform_ai_messages')->nullOnDelete();
            $table->string('source_type', 30)->default('conversation');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->unsignedTinyInteger('importance')->default(50);
            $table->boolean('confirmed')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('platform_ai_memories')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['scope', 'status', 'importance']);
            $table->index(['platform_ai_agent_id', 'category', 'status'], 'ai_memory_agent_category_status');
            $table->index(['platform_admin_id', 'status']);
        });

        Schema::create('platform_ai_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_ai_agent_id')->constrained()->cascadeOnDelete();
            $table->string('active_topic')->nullable();
            $table->string('mood', 20)->nullable();
            $table->json('state')->nullable();
            $table->timestamp('context_revised_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_admin_id', 'platform_ai_agent_id'], 'ai_conversation_state_owner_agent');
        });

        Schema::create('platform_ai_decisions', function (Blueprint $table) {
            $table->id();
            $table->text('statement');
            $table->string('business_area', 50);
            $table->text('reason')->nullable();
            $table->json('conditions')->nullable();
            $table->json('related_metrics')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->foreignId('platform_ai_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('platform_ai_messages')->nullOnDelete();
            $table->foreignId('platform_ai_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('superseded_by')->nullable()->constrained('platform_ai_decisions')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['business_area', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_decisions');
        Schema::dropIfExists('platform_ai_conversation_states');
        Schema::dropIfExists('platform_ai_memories');
        Schema::dropIfExists('platform_ai_owner_contexts');
    }
};
