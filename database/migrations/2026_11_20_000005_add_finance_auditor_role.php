<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah peran "finance_auditor" (hanya lihat & ekspor keuangan Yoka) ke enum role platform_admins.
 * Hanya MENAMBAH nilai enum; data admin yang ada tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE platform_admins MODIFY role ENUM('super_admin','support','finance','finance_auditor') NOT NULL DEFAULT 'support'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('platform_admins')->where('role', 'finance_auditor')->update(['role' => 'support']);
            DB::statement("ALTER TABLE platform_admins MODIFY role ENUM('super_admin','support','finance') NOT NULL DEFAULT 'support'");
        }
    }
};
