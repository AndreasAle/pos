<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kredit Tanya Yoka: dompet per bisnis, buku mutasi, dan top-up Midtrans. Hanya tabel baru. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('free_balance')->default(0);
            $t->char('free_period', 7)->nullable(); // YYYY-MM jatah gratis terakhir
            $t->unsignedInteger('paid_balance')->default(0);
            $t->date('paid_expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_credit_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);   // grant_monthly, topup, usage, expire_free, expire_paid, adjust
            $t->integer('free_change')->default(0);
            $t->integer('paid_change')->default(0);
            $t->unsignedInteger('free_after');
            $t->unsignedInteger('paid_after');
            $t->string('reference', 80)->nullable(); // msg:123, AIC-..., 2026-11
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['business_id', 'id']);
            $t->unique(['business_id', 'kind', 'reference']);
        });

        Schema::create('ai_credit_topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('pack', 20);
            $t->unsignedInteger('credits');
            $t->decimal('amount', 15, 2);
            $t->string('status', 20)->default('pending'); // pending, paid, cancelled
            $t->string('payment_token', 80)->unique();
            $t->text('payment_url')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        if (Schema::hasTable('platform_fin_accounts') && ! DB::table('platform_fin_accounts')->where('code', '4200')->exists()) {
            DB::table('platform_fin_accounts')->insert([
                'code' => '4200', 'name' => 'Pendapatan Kredit AI', 'type' => 'revenue', 'subtype' => 'ai_credits',
                'is_cash' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_topups');
        Schema::dropIfExists('ai_credit_entries');
        Schema::dropIfExists('ai_credit_wallets');
    }
};
