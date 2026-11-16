<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformAiTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.yoka_ai.key' => null]);
    }

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Andreas', 'email' => $role.'@yoka.test', 'password' => 'password-kuat', 'role' => $role, 'is_active' => true]);
    }

    private function platformSession(PlatformAdmin $admin): static
    {
        return $this->actingAs($admin, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_super_admin_can_open_ai_team_and_defaults_are_created(): void
    {
        $this->platformSession($this->admin())->get(route('platform.ai-team.index'))
            ->assertOk()->assertSee('Chief of Staff')->assertSee('Marketing Intelligence')->assertSee('Sales Development');

        $this->assertDatabaseCount('platform_ai_agents', 7);
    }

    public function test_non_super_admin_cannot_access_ai_control_plane(): void
    {
        $this->platformSession($this->admin('support'))->get(route('platform.ai-team.index'))->assertForbidden();
    }

    public function test_content_is_recorded_and_marketing_agent_uses_it(): void
    {
        $admin = $this->admin();
        $this->platformSession($admin)->post(route('platform.ai-team.records'), [
            'kind' => 'content', 'title' => 'Kesalahan stok kafe', 'status' => 'active',
            'source' => 'TikTok', 'score' => 84, 'url' => 'https://tiktok.com/example',
        ])->assertRedirect();

        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'marketing')->firstOrFail();
        $this->platformSession($admin)->post(route('platform.ai-team.run', $agent))->assertRedirect();

        $this->assertDatabaseHas('platform_ai_tasks', ['platform_ai_agent_id' => $agent->id, 'status' => 'completed']);
        $this->assertDatabaseHas('platform_ai_messages', ['platform_ai_agent_id' => $agent->id, 'role' => 'assistant', 'read_at' => null]);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'ai.agent.run', 'platform_admin_id' => $admin->id]);
    }

    public function test_external_agent_work_requires_recorded_approval(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $task = app(PlatformAiOperations::class)->run(PlatformAiAgent::where('key', 'ads')->firstOrFail(), true);
        $this->assertTrue($task->requires_approval);
        $this->assertSame('proposed', $task->status);

        $this->platformSession($admin)->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('platform_ai_tasks', ['id' => $task->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'ai.task.approve']);
    }

    public function test_manual_task_can_move_across_the_kanban_board(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'sales')->firstOrFail();

        $this->platformSession($admin)->post(route('platform.ai-team.tasks.store'), [
            'platform_ai_agent_id' => $agent->id,
            'title' => 'Riset prospek kafe Jakarta',
            'summary' => 'Cari prospek yang cocok tanpa mengirim pesan.',
            'risk' => 'low',
        ])->assertRedirect();

        $task = $agent->tasks()->where('type', 'manual')->firstOrFail();
        $this->platformSession($admin)->patch(route('platform.ai-team.tasks.move', $task), ['status' => 'approved'])->assertRedirect();

        $this->assertDatabaseHas('platform_ai_tasks', ['id' => $task->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'ai.task.move']);
    }

    public function test_admin_can_chat_with_an_agent_and_internal_mode_is_honest_without_api(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'finance')->firstOrFail();

        $response = $this->platformSession($admin)->postJson(route('platform.ai-team.chat', $agent), [
            'message' => 'Bagaimana kondisi margin saat ini?',
        ]);

        $response->assertOk()->assertJsonPath('assistant.mode', 'internal')
            ->assertJsonFragment(['role' => 'assistant']);
        $this->assertDatabaseHas('platform_ai_messages', ['platform_ai_agent_id' => $agent->id, 'role' => 'user']);
        $this->assertDatabaseHas('platform_ai_messages', ['platform_ai_agent_id' => $agent->id, 'role' => 'assistant']);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'ai.agent.chat']);
    }

    public function test_chat_uses_configured_model_and_keeps_agent_context(): void
    {
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Margin sementara aman, tetapi biaya harus tetap dicatat.']]],
        ])]);
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'finance')->firstOrFail();

        $this->platformSession($admin)->postJson(route('platform.ai-team.chat', $agent), ['message' => 'Apa fokus hari ini?'])
            ->assertOk()->assertJsonPath('assistant.mode', 'ai')
            ->assertJsonPath('assistant.content', 'Margin sementara aman, tetapi biaya harus tetap dicatat.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions')
            && data_get($request->data(), 'messages.0.content') !== null);
    }

    public function test_proactive_agent_message_can_be_marked_as_read(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'accounting')->firstOrFail();
        app(PlatformAiOperations::class)->run($agent, true);

        $message = $agent->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertTrue((bool) data_get($message->meta, 'proactive'));
        $this->assertNull($message->read_at);

        $this->platformSession($admin)->patchJson(route('platform.ai-team.read', $agent))->assertNoContent();
        $this->assertNotNull($message->fresh()->read_at);
    }
}
