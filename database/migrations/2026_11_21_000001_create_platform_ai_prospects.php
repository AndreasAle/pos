<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prospek Adit: daftar calon pelanggan yang diisi pemilik, dicek Adit, lalu dihubungi maks 3/hari lewat persetujuan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_prospects', function (Blueprint $t) {
            $t->id();
            $t->string('business_name', 150)->default('');
            $t->string('contact_name', 100)->default('');
            $t->string('phone', 30)->default('');
            $t->string('phone_normalized', 20)->nullable()->index();
            $t->string('business_type', 40)->default('');
            $t->string('city', 80)->default('');
            $t->unsignedSmallInteger('outlet_count')->nullable();
            $t->string('source', 120)->default(''); // dari mana nomor didapat (izin kontak)
            $t->string('notes', 500)->default('');
            // baru, perlu_dicek, siap, dijadwalkan, dihubungi, membalas, tertarik, trial, bayar, tidak_minat, berhenti
            $t->string('status', 14)->default('baru')->index();
            $t->json('check_issues')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->unsignedTinyInteger('contact_count')->default(0);
            $t->timestamp('last_contacted_at')->nullable();
            $t->timestamp('last_reply_at')->nullable();
            $t->unsignedBigInteger('matched_business_id')->nullable();
            $t->unsignedBigInteger('last_task_id')->nullable();
            $t->timestamp('opted_out_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_prospects');
    }
};
