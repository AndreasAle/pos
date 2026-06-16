<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE business_subscriptions MODIFY COLUMN status ENUM('trial','active','expired','cancelled','pending') NOT NULL DEFAULT 'trial'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE business_subscriptions MODIFY COLUMN status ENUM('trial','active','expired','cancelled') NOT NULL DEFAULT 'trial'");
    }
};
