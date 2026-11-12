<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('platform_admins', 'recovery_codes')) {
            Schema::table('platform_admins', function (Blueprint $table) {
                $table->json('recovery_codes')->nullable()->after('two_factor_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_admins', 'recovery_codes')) {
            Schema::table('platform_admins', function (Blueprint $table) {
                $table->dropColumn('recovery_codes');
            });
        }
    }
};
