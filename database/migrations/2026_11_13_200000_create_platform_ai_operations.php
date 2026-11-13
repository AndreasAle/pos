<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('department');
            $table->text('mission');
            $table->string('status')->default('active');
            $table->string('autonomy')->default('recommend');
            $table->unsignedInteger('daily_budget')->default(0);
            $table->json('permissions')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_ai_records', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('title');
            $table->string('status')->default('new');
            $table->string('source')->default('manual');
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'status']);
            $table->index(['kind', 'occurred_at']);
        });

        Schema::create('platform_ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_agent_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('summary');
            $table->text('recommendation')->nullable();
            $table->string('status')->default('proposed');
            $table->string('risk')->default('low');
            $table->boolean('requires_approval')->default(true);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'requires_approval']);
            $table->index(['platform_ai_agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_tasks');
        Schema::dropIfExists('platform_ai_records');
        Schema::dropIfExists('platform_ai_agents');
    }
};
