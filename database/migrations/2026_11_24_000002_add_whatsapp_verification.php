<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifikasi pemilik lewat kode WhatsApp (pendaftaran dari aplikasi Android).
 * Kode tidak pernah disimpan mentah: hanya HMAC-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
