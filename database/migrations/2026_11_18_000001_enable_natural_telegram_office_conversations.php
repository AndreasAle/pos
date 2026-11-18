<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_ai_telegram_groups')
            ->where('mode', 'mention_only')
            ->update(['mode' => 'smart_discussion']);
    }

    public function down(): void
    {
        // Percakapan natural tidak diturunkan otomatis agar grup aktif tidak tiba-tiba diam.
    }
};
