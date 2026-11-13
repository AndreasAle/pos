<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sheet_feeds')) {
            return;
        }

        Schema::create('sheet_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            // Pencarian lewat hash; salinan terenkripsi hanya untuk menampilkan ulang tautan ke pemilik.
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_feeds');
    }
};
