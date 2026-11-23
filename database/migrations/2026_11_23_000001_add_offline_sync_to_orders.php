<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent POS sales and the offline register (docs/offline-mode).
 *
 * client_uuid is minted by the register for every sale; the unique index makes
 * a retried request (lost response, or an offline queue replayed) land on the
 * same order instead of a second one. NULLs are allowed for every other source
 * (QR tables, platforms, old rows) and do not collide in a MySQL unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('client_uuid', 36)->nullable()->after('order_number');
            $table->string('offline_number', 40)->nullable()->after('client_uuid');
            $table->string('device_id', 64)->nullable()->after('offline_number');
            $table->timestamp('synced_at')->nullable()->after('ordered_at');
            // Why an offline sale needs a human look: price_mismatch, shift_closed,
            // underpaid, stale, stock_negative. Comma separated; NULL = clean.
            $table->string('offline_review', 120)->nullable()->after('synced_at');
            $table->json('offline_meta')->nullable()->after('offline_review');

            $table->unique(['business_id', 'client_uuid'], 'orders_business_client_uuid_unique');
            $table->index(['business_id', 'offline_review'], 'orders_offline_review_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_business_client_uuid_unique');
            $table->dropIndex('orders_offline_review_idx');
            $table->dropColumn(['client_uuid', 'offline_number', 'device_id', 'synced_at', 'offline_review', 'offline_meta']);
        });
    }
};
