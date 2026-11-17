<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_ai_tasks', function (Blueprint $table) {
            $table->foreignId('parent_task_id')->nullable()->after('platform_ai_agent_id')->constrained('platform_ai_tasks')->nullOnDelete();
            $table->foreignId('delegated_by_agent_id')->nullable()->after('parent_task_id')->constrained('platform_ai_agents')->nullOnDelete();
            $table->unsignedTinyInteger('delegation_depth')->default(0)->after('delegated_by_agent_id');
            $table->unsignedInteger('approval_version')->default(1)->after('requires_approval');
            $table->uuid('idempotency_key')->nullable()->unique()->after('approval_version');
        });

        Schema::create('platform_ai_task_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_task_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedInteger('task_version');
            $table->unsignedInteger('attempt')->default(1);
            $table->string('status', 20)->default('running');
            $table->json('tool_calls')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['platform_ai_task_id', 'status']);
            $table->index(['idempotency_key', 'status']);
        });

        Schema::create('platform_ai_observations', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 80);
            $table->json('value');
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['metric', 'observed_at']);
        });

        Schema::create('platform_ai_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_ai_agent_id');
            $table->foreign('platform_ai_agent_id', 'ai_event_agent_fk')
                ->references('id')->on('platform_ai_agents')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('severity', 20);
            $table->string('fingerprint', 64)->unique();
            $table->json('evidence');
            $table->string('status', 20)->default('open');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('platform_ai_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('platform_admins')->cascadeOnDelete();
            $table->foreignId('chair_agent_id')->nullable()->constrained('platform_ai_agents')->nullOnDelete();
            $table->string('objective');
            $table->string('status', 20)->default('pending');
            $table->json('limits');
            $table->text('synthesis')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_ai_discussion_contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_ai_discussion_id');
            $table->foreign('platform_ai_discussion_id', 'ai_discussion_contribution_discussion_fk')
                ->references('id')->on('platform_ai_discussions')->cascadeOnDelete();
            $table->unsignedBigInteger('platform_ai_agent_id');
            $table->foreign('platform_ai_agent_id', 'ai_discussion_contribution_agent_fk')
                ->references('id')->on('platform_ai_agents')->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->text('content');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->unique(['platform_ai_discussion_id', 'platform_ai_agent_id', 'round'], 'ai_discussion_agent_round');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_discussion_contributions');
        Schema::dropIfExists('platform_ai_discussions');
        Schema::dropIfExists('platform_ai_events');
        Schema::dropIfExists('platform_ai_observations');
        Schema::dropIfExists('platform_ai_task_executions');
        Schema::table('platform_ai_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_task_id');
            $table->dropConstrainedForeignId('delegated_by_agent_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['delegation_depth', 'approval_version', 'idempotency_key']);
        });
    }
};
