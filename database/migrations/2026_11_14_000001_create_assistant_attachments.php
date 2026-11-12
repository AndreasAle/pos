<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistant_attachments')) {
            return;
        }
        Schema::create('assistant_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('assistant_conversation_id')->index();
            $table->unsignedBigInteger('assistant_message_id')->nullable()->index();
            $table->string('original_name', 190);
            $table->string('mime', 100);
            $table->string('kind', 12); // text | image | pdf
            $table->unsignedInteger('size');
            $table->string('path', 255);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_attachments');
    }
};
