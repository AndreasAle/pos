<?php

namespace Tests\Feature\Platform;

use App\Jobs\RunPlatformAiTask;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDecision;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiMemory;
use App\Models\PlatformAiRun;
use App\Services\PlatformAiContext;
use App\Services\PlatformAiDiscussions;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAiWorkforceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Andreas', 'email' => 'owner@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    public function test_temporary_mood_and_correction_guidance_are_bounded_context(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();

        $urgent = app(PlatformAiContext::class)->package($agent, $admin, 'Urgent, jawab cepat.')['current_signal'];
        $correction = app(PlatformAiContext::class)->package($agent, $admin, 'Bukan itu maksudku, angkanya salah.')['current_signal'];

        $this->assertSame('urgent', $urgent['mood']);
        $this->assertTrue($correction['correction']);
        $this->assertStringContainsString('ambil ulang fakta', $correction['response_guidance']);
    }

    public function test_provider_usage_latency_tool_calls_and_estimated_cost_are_recorded(): void
    {
        config(['services.yoka_ai.key' => 'test', 'services.yoka_ai.url' => 'https://ai.test/v1', 'services.yoka_ai.input_cost_per_million' => 1, 'services.yoka_ai.output_cost_per_million' => 2]);
        Http::fakeSequence('https://ai.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'm1', 'type' => 'function', 'function' => ['name' => 'business_metrics', 'arguments' => '{}']]]]]], 'usage' => ['prompt_tokens' => 600, 'completion_tokens' => 100]])
            ->push(['choices' => [['message' => ['content' => 'Jawaban terukur.']]], 'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 400]]);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'finance')->firstOrFail();

        app(PlatformAiOperations::class)->reply($agent, 'Ringkas kondisi.', [], [], $this->admin());

        $this->assertSame(1000, (int) PlatformAiRun::sum('input_tokens'));
        $this->assertSame(500, (int) PlatformAiRun::sum('output_tokens'));
        $this->assertSame(1, (int) PlatformAiRun::sum('tool_calls'));
        $this->assertEqualsWithDelta(0.002, (float) PlatformAiRun::sum('estimated_cost_usd'), 0.000001);
        $this->assertTrue((bool) data_get(PlatformAiRun::first()->metadata, 'cost_is_estimate'));
    }

    public function test_daily_cost_guard_stops_discretionary_model_call(): void
    {
        config(['services.yoka_ai.key' => 'test', 'services.yoka_ai.daily_cost_budget_usd' => 1]);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'marketing')->firstOrFail();
        PlatformAiRun::create(['platform_ai_agent_id' => $agent->id, 'operation' => 'chat', 'model' => 'test', 'status' => 'completed', 'estimated_cost_usd' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Http::fake();

        $result = app(PlatformAiOperations::class)->reply($agent, 'Buat ide.', [], [], $this->admin());

        $this->assertSame('fallback', $result['mode']);
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_observed_and_falls_back_without_fake_success(): void
    {
        config(['services.yoka_ai.key' => 'test', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['error' => 'upstream unavailable'], 503)]);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'sales')->firstOrFail();

        $result = app(PlatformAiOperations::class)->reply($agent, 'Sudah hubungi lead?', [], [], $this->admin());

        $this->assertSame('fallback', $result['mode']);
        $this->assertStringContainsString('bermasalah', $result['content']);
        $this->assertDatabaseHas('platform_ai_runs', ['platform_ai_agent_id' => $agent->id, 'status' => 'failed', 'operation' => 'chat']);
        $this->assertNotNull(PlatformAiRun::first()->error);
    }

    public function test_four_agent_end_to_end_discussion_persists_only_real_participants(): void
    {
        config(['services.yoka_ai.key' => null]);
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agents = PlatformAiAgent::whereIn('key', ['chief', 'marketing', 'finance', 'sales'])->get();
        $discussion = PlatformAiDiscussion::create(['created_by' => $admin->id, 'chair_agent_id' => $agents->firstWhere('key', 'chief')->id, 'objective' => 'Tentukan strategi pertumbuhan trial yang aman.', 'status' => 'pending', 'limits' => ['max_agents' => 4, 'max_rounds' => 1]]);

        app(PlatformAiDiscussions::class)->run($discussion, $admin, $agents->pluck('id')->all());

        $discussion->refresh();
        $this->assertSame('completed', $discussion->status);
        $this->assertSame(4, $discussion->contributions()->count());
        foreach (['Ramli', 'Yusuf', 'Luna', 'Yosef'] as $name) {
            $this->assertStringContainsString($name, $discussion->synthesis);
        }
        $this->assertDatabaseCount('platform_ai_tasks', 0);
    }

    public function test_atomic_request_budget_limits_burst_of_jobs(): void
    {
        config(['services.yoka_ai.key' => 'test', 'services.yoka_ai.url' => 'https://ai.test/v1', 'services.yoka_ai.daily_request_budget' => 2, 'services.yoka_ai.daily_cost_budget_usd' => 100]);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $admin = $this->admin();

        $modes = collect(range(1, 5))->map(fn ($i) => app(PlatformAiOperations::class)->reply($agent, "job {$i}", [], [], $admin)['mode']);

        $this->assertSame(2, $modes->filter(fn ($mode) => $mode === 'ai')->count());
        $this->assertSame(3, $modes->filter(fn ($mode) => $mode === 'internal')->count());
        Http::assertSentCount(2);
    }

    public function test_timeout_retry_creates_two_attempts_but_only_one_completion_and_redacts_error(): void
    {
        Log::spy();
        config(['services.yoka_ai.key' => 'test', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $key = (string) Str::uuid();
        $task = $agent->tasks()->create(['type' => 'manual', 'title' => 'Retry aman', 'summary' => 'Kerjakan.', 'risk' => 'low', 'status' => 'approved', 'requires_approval' => true, 'idempotency_key' => $key]);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new ConnectionException('timeout token=rahasia owner@example.com Bearer abc123');
            }

            return Http::response(['choices' => [['message' => ['content' => 'Selesai setelah retry.']]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]);
        });

        app(PlatformAiOperations::class)->execute($task->id, $key);
        app(PlatformAiOperations::class)->execute($task->id, $key);

        $this->assertSame(2, $task->executions()->count());
        $this->assertSame(1, $task->executions()->where('status', 'completed')->count());
        $this->assertSame('completed', $task->fresh()->status);
        $error = PlatformAiRun::where('status', 'failed')->value('error');
        $this->assertStringNotContainsString('rahasia', $error);
        $this->assertStringNotContainsString('owner@example.com', $error);
        $this->assertStringNotContainsString('abc123', $error);
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $encoded = json_encode($context);

            return $message === 'Platform AI task execution failed'
                && ! str_contains($encoded, 'rahasia')
                && ! str_contains($encoded, 'owner@example.com')
                && ! str_contains($encoded, 'abc123');
        })->once();
    }

    public function test_queue_recovery_dispatches_interrupted_task_once_after_restart(): void
    {
        Bus::fake();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $task = $agent->tasks()->create(['type' => 'manual', 'title' => 'Pulihkan', 'summary' => 'Lanjutkan.', 'risk' => 'low', 'status' => 'approved', 'requires_approval' => true, 'idempotency_key' => (string) Str::uuid()]);

        $this->assertSame(1, app(PlatformAiOperations::class)->recoverPendingExecutions());
        Bus::assertDispatchedAfterResponse(RunPlatformAiTask::class, fn ($job) => $job->taskId === $task->id);
    }

    public function test_memory_and_decision_remain_consistent_across_fresh_model_sessions(): void
    {
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'ads')->firstOrFail();
        $message = $agent->messages()->create(['platform_admin_id' => $admin->id, 'role' => 'user', 'content' => 'Jangan naikkan budget sampai konversi membaik.']);
        app(PlatformAiContext::class)->rememberExplicitOwnerStatement($agent, $admin, $message, $message->content);

        $package = app(PlatformAiContext::class)->package(PlatformAiAgent::findOrFail($agent->id), PlatformAdmin::findOrFail($admin->id), 'Apa batas budget?');

        $this->assertSame(1, PlatformAiMemory::where('status', 'active')->count());
        $this->assertSame(1, PlatformAiDecision::where('status', 'active')->count());
        $this->assertStringContainsString('Jangan naikkan budget', data_get($package, 'active_decisions.0.statement'));
    }
}
