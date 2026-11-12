<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_type', 80);
            $table->json('payload');
            $table->json('preview');
            $table->string('payload_hash', 64);
            $table->string('expected_version');
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'expires_at'], 'intel_actions_scope_status_idx');
        });

        Schema::create('intelligence_action_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('intelligence_action_proposals')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 20);
            $table->string('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('intelligence_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('object_type', 80);
            $table->unsignedBigInteger('object_id');
            $table->string('event', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('happened_at');
            $table->index(['business_id', 'happened_at']);
        });

        Schema::create('intelligence_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_key', 60);
            $table->string('frequency', 20)->default('daily');
            $table->string('schedule_time', 5)->nullable();
            $table->string('timezone');
            $table->json('recipients')->nullable();
            $table->json('channels')->nullable();
            $table->json('thresholds')->nullable();
            $table->unsignedSmallInteger('suppression_minutes')->default(60);
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'outlet_id', 'agent_key'], 'intelligence_agents_scope_key');
        });

        Schema::create('intelligence_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('intelligence_agents')->cascadeOnDelete();
            $table->string('run_key', 100)->unique();
            $table->string('status', 20)->default('running');
            $table->timestamp('scheduled_for');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('output')->nullable();
            $table->string('error_code')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamps();
            $table->index(['agent_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_agent_runs');
        Schema::dropIfExists('intelligence_agents');
        Schema::dropIfExists('intelligence_audit_events');
        Schema::dropIfExists('intelligence_action_approvals');
        Schema::dropIfExists('intelligence_action_proposals');
    }
};
