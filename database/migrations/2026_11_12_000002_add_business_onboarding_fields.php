<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('requires_business_setup')->default(false)->after('is_active');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type', 40)->nullable()->after('name');
            $table->string('outlet_scale', 20)->nullable()->after('business_type');
            $table->string('city', 100)->nullable()->after('address');
            $table->timestamp('onboarding_completed_at')->nullable()->after('settings');
            $table->timestamp('dashboard_tour_completed_at')->nullable()->after('onboarding_completed_at');
        });

        // Existing tenants are already configured and must not be interrupted.
        DB::table('businesses')->update([
            'onboarding_completed_at' => now(),
            'dashboard_tour_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'business_type',
                'outlet_scale',
                'city',
                'onboarding_completed_at',
                'dashboard_tour_completed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('requires_business_setup');
        });
    }
};
