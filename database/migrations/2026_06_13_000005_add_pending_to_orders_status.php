<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('unpaid','paid','refunded','pending') NOT NULL DEFAULT 'unpaid'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('draft','paid','cancelled','refunded','pending','void') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('draft','paid','cancelled','refunded') NOT NULL DEFAULT 'draft'");
    }
};
