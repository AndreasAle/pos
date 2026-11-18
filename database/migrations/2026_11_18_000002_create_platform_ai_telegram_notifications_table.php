<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger notifikasi Telegram Office. Kunci unik (dedupe_key) memastikan satu keadaan tugas
 * hanya menghasilkan satu notifikasi per grup/topik, termasuk saat beberapa queue worker
 * memproses event yang sama bersamaan (klaim lewat INSERT, bukan cek-lalu-tulis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_telegram_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->string('type', 40);
            // Nama constraint ditulis manual: nama otomatis Laravel melebihi batas 64 karakter MySQL.
            $table->unsignedBigInteger('platform_ai_telegram_group_id')->nullable();
            $table->foreign('platform_ai_telegram_group_id', 'ai_tg_notif_group_fk')->references('id')->on('platform_ai_telegram_groups')->nullOnDelete();
            $table->unsignedBigInteger('platform_ai_task_id')->nullable();
            $table->foreign('platform_ai_task_id', 'ai_tg_notif_task_fk')->references('id')->on('platform_ai_tasks')->nullOnDelete();
            $table->unsignedInteger('task_version')->nullable();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('thread_id')->nullable();
            $table->string('event_fingerprint', 64)->nullable();
            $table->string('status', 20)->default('claimed');
            $table->unsignedBigInteger('platform_ai_telegram_message_id')->nullable();
            $table->foreign('platform_ai_telegram_message_id', 'ai_tg_notif_message_fk')->references('id')->on('platform_ai_telegram_messages')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'platform_ai_telegram_group_id', 'created_at'], 'ai_tg_notif_type_group_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_telegram_notifications');
    }
};
