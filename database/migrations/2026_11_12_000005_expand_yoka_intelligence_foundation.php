<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('context_version')->default(1)->after('title');
            $table->json('context')->nullable()->after('context_version');
            $table->text('summary')->nullable()->after('context');
            $table->timestamp('last_message_at')->nullable()->after('summary');
            $table->index(['business_id', 'outlet_id', 'last_message_at'], 'assistant_conversations_scope_idx');
        });

        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->json('response')->nullable()->after('meta');
            $table->json('token_usage')->nullable()->after('response');
            $table->string('request_id')->nullable()->after('token_usage');
            $table->unsignedInteger('duration_ms')->nullable()->after('request_id');
        });

        Schema::create('assistant_tool_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name', 80);
            $table->string('schema_version', 20)->default('1.0');
            $table->string('args_hash', 64);
            $table->json('source_filters');
            $table->json('evidence')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('succeeded');
            $table->string('error_code')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
        });

        Schema::table('business_insights', function (Blueprint $table) {
            $table->json('evidence')->nullable()->after('data');
            $table->unsignedTinyInteger('confidence')->nullable()->after('evidence');
            $table->unsignedInteger('sample_size')->nullable()->after('confidence');
            $table->unsignedInteger('occurrence_count')->default(1)->after('sample_size');
            $table->timestamp('first_detected_at')->nullable()->after('detected_at');
            $table->timestamp('last_detected_at')->nullable()->after('first_detected_at');
            $table->timestamp('acknowledged_at')->nullable()->after('read_at');
            $table->timestamp('snoozed_until')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_insights', function (Blueprint $table) {
            $table->dropColumn(['evidence', 'confidence', 'sample_size', 'occurrence_count', 'first_detected_at', 'last_detected_at', 'acknowledged_at', 'snoozed_until']);
        });
        Schema::dropIfExists('assistant_tool_runs');
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropColumn(['response', 'token_usage', 'request_id', 'duration_ms']);
        });
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropIndex('assistant_conversations_scope_idx');
            $table->dropConstrainedForeignId('outlet_id');
            $table->dropColumn(['context_version', 'context', 'summary', 'last_message_at']);
        });
    }
};
