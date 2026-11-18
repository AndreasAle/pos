<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_ai_conversation_states', function (Blueprint $table) {
            $table->index('platform_admin_id', 'ai_state_admin_fk_index');
            $table->index('platform_ai_agent_id', 'ai_state_agent_fk_index');
        });
        Schema::table('platform_ai_conversation_states', function (Blueprint $table) {
            $table->dropUnique('ai_conversation_state_owner_agent');
            $table->string('channel', 30)->default('platform_dashboard')->after('platform_ai_agent_id');
            $table->string('context_key', 120)->default('default')->after('channel');
            $table->unique(['platform_admin_id', 'platform_ai_agent_id', 'channel', 'context_key'], 'ai_state_owner_agent_channel_context');
        });
        Schema::table('platform_ai_runs', function (Blueprint $table) {
            $table->string('channel', 30)->default('platform_dashboard')->after('operation');
            $table->uuid('interaction_id')->nullable()->after('channel')->index();
        });

        Schema::create('platform_ai_telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('public_key')->unique();
            $table->bigInteger('telegram_bot_id')->nullable()->unique();
            $table->string('username')->nullable();
            $table->string('credential_key', 40)->unique();
            $table->string('webhook_secret_hash');
            $table->string('status', 20)->default('inactive');
            $table->string('webhook_status', 20)->default('pending');
            $table->timestamp('last_health_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_ai_telegram_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_user_id')->nullable()->unique();
            $table->string('telegram_username')->nullable();
            $table->string('link_code_hash')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['platform_admin_id', 'status']);
        });

        Schema::create('platform_ai_telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_chat_id')->unique();
            $table->string('title')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('mode', 30)->default('smart_discussion');
            $table->json('topic_routes')->nullable();
            $table->json('notification_settings')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_ai_telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_telegram_bot_id');
            $table->foreignId('platform_ai_telegram_group_id')->nullable();
            $table->foreign('platform_ai_telegram_bot_id', 'tg_updates_bot_fk')->references('id')->on('platform_ai_telegram_bots')->cascadeOnDelete();
            $table->foreign('platform_ai_telegram_group_id', 'tg_updates_group_fk')->references('id')->on('platform_ai_telegram_groups')->nullOnDelete();
            $table->unsignedBigInteger('telegram_update_id');
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->uuid('interaction_id');
            $table->string('event_fingerprint', 64)->unique();
            $table->string('status', 20)->default('received');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_ai_telegram_bot_id', 'telegram_update_id'], 'telegram_bot_update_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('platform_ai_telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_telegram_group_id')->nullable();
            $table->foreignId('platform_ai_telegram_bot_id')->nullable();
            $table->foreignId('platform_ai_agent_id')->nullable();
            $table->foreignId('platform_admin_id')->nullable();
            $table->foreignId('platform_ai_task_id')->nullable();
            $table->foreignId('platform_ai_discussion_id')->nullable();
            $table->foreign('platform_ai_telegram_group_id', 'tg_messages_group_fk')->references('id')->on('platform_ai_telegram_groups')->nullOnDelete();
            $table->foreign('platform_ai_telegram_bot_id', 'tg_messages_bot_fk')->references('id')->on('platform_ai_telegram_bots')->nullOnDelete();
            $table->foreign('platform_ai_agent_id', 'tg_messages_agent_fk')->references('id')->on('platform_ai_agents')->nullOnDelete();
            $table->foreign('platform_admin_id', 'tg_messages_admin_fk')->references('id')->on('platform_admins')->nullOnDelete();
            $table->foreign('platform_ai_task_id', 'tg_messages_task_fk')->references('id')->on('platform_ai_tasks')->nullOnDelete();
            $table->foreign('platform_ai_discussion_id', 'tg_messages_discussion_fk')->references('id')->on('platform_ai_discussions')->nullOnDelete();
            $table->uuid('interaction_id')->nullable()->index();
            $table->string('direction', 10);
            $table->string('channel', 30)->default('telegram_group');
            $table->bigInteger('telegram_message_id')->nullable();
            $table->bigInteger('thread_id')->nullable();
            $table->text('content')->nullable();
            $table->string('delivery_status', 20)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_ai_telegram_discussion_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_ai_discussion_id');
            $table->foreignId('platform_ai_telegram_group_id');
            $table->foreign('platform_ai_discussion_id', 'tg_bindings_discussion_fk')->references('id')->on('platform_ai_discussions')->cascadeOnDelete();
            $table->foreign('platform_ai_telegram_group_id', 'tg_bindings_group_fk')->references('id')->on('platform_ai_telegram_groups')->cascadeOnDelete();
            $table->unique('platform_ai_discussion_id', 'tg_binding_discussion_unique');
            $table->uuid('interaction_id')->unique();
            $table->bigInteger('trigger_message_id')->nullable();
            $table->bigInteger('thread_id')->nullable();
            $table->json('participant_ids')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_telegram_discussion_bindings');
        Schema::dropIfExists('platform_ai_telegram_messages');
        Schema::dropIfExists('platform_ai_telegram_updates');
        Schema::dropIfExists('platform_ai_telegram_groups');
        Schema::dropIfExists('platform_ai_telegram_identities');
        Schema::dropIfExists('platform_ai_telegram_bots');
        Schema::table('platform_ai_runs', fn (Blueprint $table) => $table->dropColumn(['channel', 'interaction_id']));
        Schema::table('platform_ai_conversation_states', function (Blueprint $table) {
            $table->dropUnique('ai_state_owner_agent_channel_context');
            $table->dropColumn(['channel', 'context_key']);
            $table->unique(['platform_admin_id', 'platform_ai_agent_id'], 'ai_conversation_state_owner_agent');
        });
    }
};
