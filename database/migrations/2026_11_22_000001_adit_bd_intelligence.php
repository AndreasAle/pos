<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adit BD 2.0: tahap penjualan terpisah dari status kontak, jejak audit, dan proposal komersial berversi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_ai_prospects', function (Blueprint $t) {
            $t->string('sales_stage', 30)->default('new_lead')->index()->after('status');
            $t->unsignedInteger('stage_version')->default(0)->after('sales_stage');
            $t->json('intelligence')->nullable()->after('check_issues');
            $t->timestamp('qualified_at')->nullable()->after('intelligence');
        });

        Schema::create('platform_ai_prospect_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_ai_prospect_id')->constrained('platform_ai_prospects')->cascadeOnDelete();
            $t->string('type', 30); // transition, objection, facts, policy_denial, proposal, decision
            $t->string('from_stage', 30)->nullable();
            $t->string('to_stage', 30)->nullable();
            $t->string('actor', 40);
            $t->string('reason', 255)->default('');
            $t->json('data')->nullable();
            $t->string('idempotency_key', 100)->nullable()->unique();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['platform_ai_prospect_id', 'type'], 'ai_pevent_prospect_type_index');
        });

        Schema::create('platform_ai_commercial_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_ai_prospect_id')->constrained('platform_ai_prospects', 'id', 'ai_cprop_prospect_fk')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 20)->default('pending_approval')->index(); // pending_approval, approved, rejected, changes_requested, superseded
            $t->string('decision_state', 30); // NEEDS_HUMAN_APPROVAL, INSUFFICIENT_DATA, NOT_COMMERCIALLY_VIABLE, ...
            $t->decimal('requested_price', 12, 2)->nullable();
            $t->string('official_plan', 60)->nullable();
            $t->decimal('official_price', 12, 2)->nullable();
            $t->json('terms');
            $t->json('analysis');
            $t->char('terms_hash', 64);
            $t->unsignedBigInteger('platform_ai_task_id')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 600)->nullable();
            $t->timestamps();
            $t->unique(['platform_ai_prospect_id', 'version'], 'ai_cprop_prospect_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_commercial_proposals');
        Schema::dropIfExists('platform_ai_prospect_events');
        Schema::table('platform_ai_prospects', fn (Blueprint $t) => $t->dropColumn(['sales_stage', 'stage_version', 'intelligence', 'qualified_at']));
    }
};
