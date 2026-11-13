<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_integrations')) {
            return;
        }

        Schema::create('channel_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);                 // gofood | shopeefood
            $table->string('merchant_id', 100)->nullable(); // ID merchant di GoBiz / ShopeeFood
            $table->string('merchant_name', 150)->nullable();
            // pending = menunggu aktivasi, connected = tersambung, disconnected = diputus
            $table->string('status', 20)->default('pending');
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('consented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_integrations');
    }
};
