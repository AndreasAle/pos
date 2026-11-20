<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rekonsiliasi bank dan Midtrans per transaksi. Hanya tabel baru; tidak mengubah tabel lama. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fin_statement_lines', function (Blueprint $t) {
            $t->id();
            $t->string('account_code', 10)->index(); // 1100 bank, 1200 Midtrans, 1000 kas
            $t->date('txn_date');
            $t->string('description', 200);
            $t->decimal('amount', 16, 2); // + uang masuk, - uang keluar (sudut pandang rekening)
            $t->string('reference', 120)->nullable();
            $t->char('fingerprint', 64)->unique(); // baris yang sama tidak bisa diimpor dua kali
            $t->string('status', 10)->default('unmatched')->index(); // unmatched, matched, ignored
            $t->foreignId('platform_fin_line_id')->nullable()->unique()->constrained('platform_fin_lines');
            $t->string('match_method', 10)->nullable(); // auto, manual
            $t->string('note', 200)->nullable();
            $t->string('import_batch', 40)->index();
            $t->foreignId('imported_by')->nullable()->constrained('platform_admins');
            $t->foreignId('resolved_by')->nullable()->constrained('platform_admins');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fin_statement_lines');
    }
};
