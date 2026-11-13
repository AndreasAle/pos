<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('channel_integrations', 'access_token')) {
                // Token dienkripsi di level model (cast "encrypted"), jadi kolomnya text.
                $table->text('access_token')->nullable()->after('status');
                $table->text('refresh_token')->nullable()->after('access_token');
                $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
                $table->string('scopes', 500)->nullable()->after('token_expires_at');
                $table->timestamp('connected_at')->nullable()->after('scopes');
                $table->string('last_error', 500)->nullable()->after('connected_at');
            }
        });

        // Satu ID merchant/toko hanya boleh terdaftar sekali per platform. Tanpa ini,
        // tenant lain bisa mendaftarkan ID toko orang lain dan ikut menerima pesanannya.
        Schema::table('channel_integrations', function (Blueprint $table) {
            $table->unique(['provider', 'merchant_id'], 'channel_integrations_provider_merchant_unique');
        });

        if (! Schema::hasTable('channel_orders')) {
            Schema::create('channel_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
                $table->foreignId('channel_integration_id')->constrained('channel_integrations')->cascadeOnDelete();
                $table->string('provider', 20);
                $table->string('external_order_id', 64);
                $table->string('short_code', 30)->nullable();
                $table->string('status', 40);
                $table->json('payload')->nullable();
                // Diisi saat pesanan sudah dibuat menjadi order POS.
                $table->unsignedBigInteger('order_id')->nullable();
                $table->timestamp('received_at')->useCurrent();
                $table->timestamps();

                $table->unique(['provider', 'external_order_id']);
                $table->index(['business_id', 'outlet_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_orders');

        Schema::table('channel_integrations', function (Blueprint $table) {
            $table->dropUnique('channel_integrations_provider_merchant_unique');
            $table->dropColumn(['access_token', 'refresh_token', 'token_expires_at', 'scopes', 'connected_at', 'last_error']);
        });
    }
};
