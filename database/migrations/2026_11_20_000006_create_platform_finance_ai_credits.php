<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kredit dan biaya OpenAI persis dari Costs API organisasi. Hanya tabel baru. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fin_ai_usage', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20)->default('openai');
            $t->date('usage_date');
            $t->string('line_item', 150)->default('');
            $t->string('project_id', 80)->default('');
            $t->decimal('amount_usd', 14, 6);
            $t->timestamp('synced_at');
            $t->timestamps();
            $t->unique(['provider', 'usage_date', 'line_item', 'project_id'], 'pfau_unique');
        });

        Schema::create('platform_fin_ai_credits', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20)->default('openai');
            $t->string('kind', 10); // opening, purchase, grant
            $t->date('credited_at');
            $t->decimal('amount_usd', 12, 2);
            $t->decimal('amount_idr', 16, 2)->default(0);
            $t->string('paid_from', 20)->nullable();
            $t->date('expires_at')->nullable();
            $t->string('reference', 120)->nullable();
            $t->foreignId('platform_fin_entry_id')->nullable()->constrained('platform_fin_entries');
            $t->foreignId('created_by')->nullable()->constrained('platform_admins');
            $t->timestamps();
        });

        Schema::create('platform_fin_ai_expensed', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20)->default('openai');
            $t->char('period', 7);
            $t->decimal('amount_usd', 14, 6);
            $t->decimal('amount_idr', 16, 2);
            $t->foreignId('platform_fin_expense_id')->constrained('platform_fin_expenses');
            $t->timestamps();
            $t->unique(['provider', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fin_ai_expensed');
        Schema::dropIfExists('platform_fin_ai_credits');
        Schema::dropIfExists('platform_fin_ai_usage');
    }
};
