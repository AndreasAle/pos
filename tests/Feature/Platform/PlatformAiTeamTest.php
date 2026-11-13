<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
