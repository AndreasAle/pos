<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Harga paket per durasi (mis. {"3": 399000, "12": 1399000}). Durasi 1 bulan selalu = price.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('period_prices')->nullable()->after('price');
        });

        // Durasi yang dibeli. null = langganan lama (dihitung dari tanggal).
        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('months')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('period_prices');
        });
        Schema::table('business_subscriptions', function (Blueprint $table) {
            $table->dropColumn('months');
        });
    }
};
