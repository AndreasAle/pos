<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Perencanaan (versi anggaran/skenario), aset tetap + penyusutan, jadwal pengakuan pendapatan. Hanya tabel baru. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fin_budget_versions', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('scenario', 12); // conservative, base, growth
            $t->char('period_from', 7);
            $t->unsignedTinyInteger('months');
            $t->string('status', 10)->default('draft')->index(); // draft, approved, archived, rejected
            $t->json('assumptions');
            $t->string('notes', 500)->nullable();
            $t->string('created_by_type', 10); // admin | agent
            $t->unsignedBigInteger('created_by_id');
            $t->foreignId('approved_by')->nullable()->constrained('platform_admins');
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('platform_fin_budget_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_fin_budget_version_id')->constrained('platform_fin_budget_versions')->cascadeOnDelete();
            $t->char('period', 7);
            $t->string('account_code', 10);
            $t->decimal('amount', 16, 2);
            $t->unique(['platform_fin_budget_version_id', 'period', 'account_code'], 'pfbl_version_period_account');
        });

        Schema::create('platform_fin_assets', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->date('acquired_at');
            $t->decimal('cost', 16, 2);
            $t->decimal('salvage_value', 16, 2)->default(0);
            $t->unsignedSmallInteger('useful_life_months');
            $t->string('paid_from', 20);
            $t->string('cost_center', 30)->nullable();
            $t->foreignId('acquisition_entry_id')->nullable()->constrained('platform_fin_entries');
            $t->string('status', 10)->default('active'); // active, disposed
            $t->foreignId('created_by')->nullable()->constrained('platform_admins');
            $t->timestamps();
        });

        Schema::create('platform_fin_depreciations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_fin_asset_id')->constrained('platform_fin_assets');
            $t->char('period', 7);
            $t->decimal('amount', 16, 2);
            $t->foreignId('platform_fin_entry_id')->constrained('platform_fin_entries');
            $t->timestamps();
            $t->unique(['platform_fin_asset_id', 'period']);
        });

        Schema::create('platform_fin_revenue_schedules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_subscription_id')->index();
            $t->char('period', 7);
            $t->decimal('amount', 16, 2);
            $t->foreignId('platform_fin_entry_id')->nullable()->constrained('platform_fin_entries');
            $t->timestamps();
            $t->unique(['business_subscription_id', 'period'], 'pfrs_sub_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fin_revenue_schedules');
        Schema::dropIfExists('platform_fin_depreciations');
        Schema::dropIfExists('platform_fin_assets');
        Schema::dropIfExists('platform_fin_budget_lines');
        Schema::dropIfExists('platform_fin_budget_versions');
    }
};
