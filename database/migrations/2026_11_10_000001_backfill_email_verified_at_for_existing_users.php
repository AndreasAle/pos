<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Konfirmasi email baru diwajibkan untuk pendaftar baru. Akun yang sudah ada
     * (mis. toko yang sudah live) dianggap terverifikasi supaya tidak terkunci.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Tidak ada yang perlu dikembalikan.
    }
};
