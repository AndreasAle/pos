<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titik acuan biaya untuk saldo awal kredit OpenAI: biaya yang sudah dipotong dashboard
 * saat saldo awal dicatat tidak boleh dikurangi lagi. Hanya kolom baru di tabel milik Keuangan Yoka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fin_ai_credits', function (Blueprint $t) {
            $t->decimal('usage_baseline_usd', 14, 6)->default(0)->after('amount_idr');
        });
    }

    public function down(): void
    {
        Schema::table('platform_fin_ai_credits', function (Blueprint $t) {
            $t->dropColumn('usage_baseline_usd');
        });
    }
};
