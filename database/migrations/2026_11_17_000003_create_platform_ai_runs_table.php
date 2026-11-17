<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_ai_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_ai_discussion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 40);
            $table->string('model')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('tool_calls')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['platform_ai_agent_id', 'created_at'], 'ai_runs_agent_created');
            $table->index(['operation', 'status', 'created_at'], 'ai_runs_operation_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_runs');
    }
};
