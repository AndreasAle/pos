<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiTask;
use App\Models\SubscriptionPlan;
use App\Models\PlatformAiAgent;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'Namamu Luna')
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'Finance Strategist')
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'Panggil dia "Bos"')
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'KEAHLIAN'));
    }

    public function test_admin_can_attach_a_file_and_agent_can_read_its_contents(): void
    {
        Storage::fake('local');
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Aku menemukan omzet Rp 1.250.000 dari laporan ini.']]],
        ])]);
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'accounting')->firstOrFail();

        $response = $this->platformSession($admin)->post(route('platform.ai-team.chat', $agent), [
            'message' => 'Nanda, tolong cek laporan ini.',
            'files' => [UploadedFile::fake()->createWithContent('laporan.csv', "tanggal,omzet\n2026-11-16,1250000")],
        ]);

        $response->assertOk()
            ->assertJsonPath('user.attachments.0.name', 'laporan.csv')
            ->assertJsonPath('assistant.content', 'Aku menemukan omzet Rp 1.250.000 dari laporan ini.');
        $this->assertDatabaseHas('platform_ai_attachments', [
            'platform_ai_agent_id' => $agent->id,
            'original_name' => 'laporan.csv',
            'kind' => 'text',
        ]);
        Http::assertSent(fn ($request) => str_contains(
            json_encode($request->data(), JSON_UNESCAPED_UNICODE),
            '2026-11-16,1250000'
        ));
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

    public function test_proactive_message_uses_the_agents_human_chat_copy(): void
    {
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 'Belum ada data konten.',
                'recommendation' => 'Masukkan tiga konten terakhir.',
                'priorities' => ['Tambah data'],
                'message' => 'Pak, bahan konten kita masih kosong. Masukkan tiga video terakhir, nanti saya bantu memilih mana yang paling layak diuji.',
            ])]]],
        ])]);
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'marketing')->firstOrFail();

        app(PlatformAiOperations::class)->run($agent, true);

        $this->assertDatabaseHas('platform_ai_messages', [
            'platform_ai_agent_id' => $agent->id,
            'content' => 'Pak, bahan konten kita masih kosong. Masukkan tiga video terakhir, nanti saya bantu memilih mana yang paling layak diuji.',
        ]);
    }

    private function trialBusiness(int $daysLeft = 3): Business
    {
        $b = Business::factory()->create(['name' => 'Kopi Senja', 'phone' => '081234567890']);
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'test-plan'], ['name' => 'Test', 'price' => 0, 'max_outlets' => 1, 'max_users' => 5]);
        BusinessSubscription::create(['business_id' => $b->id, 'subscription_plan_id' => $plan->id, 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays($daysLeft), 'status' => 'trial']);

        return $b;
    }

    private function runCs(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        app(PlatformAiOperations::class)->run(PlatformAiAgent::where('key', 'cs')->firstOrFail(), true);
    }

    public function test_cs_agent_drafts_trial_whatsapp_but_never_sends_without_approval(): void
    {
        Http::fake();
        $this->trialBusiness();
        $this->runCs();
        $this->runCs();

        $this->assertSame(1, PlatformAiTask::where('type', 'whatsapp_draft')->count());
        $this->assertDatabaseHas('platform_ai_tasks', ['type' => 'whatsapp_draft', 'status' => 'proposed', 'requires_approval' => true]);
        Http::assertNothingSent();
    }

    public function test_trial_follow_up_ignores_trials_that_are_not_close_to_ending(): void
    {
        $this->trialBusiness(30);
        $this->runCs();

        $this->assertDatabaseMissing('platform_ai_tasks', ['type' => 'whatsapp_draft']);
    }

    public function test_approving_a_draft_sends_the_edited_message_once(): void
    {
        config(['services.yoka_fonnte.token' => 'tok']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->trialBusiness();
        $admin = $this->admin();
        $this->runCs();
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->firstOrFail();

        $this->platformSession($admin)->patch(route('platform.ai-team.tasks.move', $task), ['status' => 'completed'])->assertStatus(422);
        Http::assertNothingSent();

        $this->platformSession($admin)->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve', 'message' => 'Halo, pesan sunting.'])->assertRedirect();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fonnte') && $r['target'] === '6281234567890' && $r['message'] === 'Halo, pesan sunting.');
        $this->assertDatabaseHas('platform_ai_tasks', ['id' => $task->id, 'status' => 'completed']);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'ai.whatsapp.sent']);
    }

    public function test_approval_without_whatsapp_token_keeps_task_approved_with_error(): void
    {
        config(['services.yoka_fonnte.token' => null]);
        Http::fake();
        $this->trialBusiness();
        $this->runCs();
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->firstOrFail();

        $this->platformSession($this->admin())->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('platform_ai_tasks', ['id' => $task->id, 'status' => 'approved']);
        $this->assertNotNull($task->fresh()->error);
        Http::assertNothingSent();
    }

    public function test_chat_agent_reads_real_tasks_through_tools_and_can_propose_a_task(): void
    {
        config(['services.yoka_ai.key' => 'k', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        $admin = $this->admin();
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $agent->tasks()->create(['type' => 'manual', 'title' => 'Putuskan harga paket tahunan', 'summary' => 'Perlu keputusan harga.', 'risk' => 'high', 'status' => 'proposed', 'requires_approval' => true]);

        Http::fakeSequence('https://ai.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'list_tasks', 'arguments' => '{}']]]]]]])
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c2', 'type' => 'function', 'function' => ['name' => 'create_task', 'arguments' => json_encode(['title' => 'Strategi follow-up trial', 'summary' => 'Hubungi trial H-3.', 'kind' => 'strategy'])]]]]]]])
            ->push(['choices' => [['message' => ['content' => 'Satu keputusan nunggu: harga paket tahunan. Strateginya sudah kuajukan, tinggal kamu setujui.']]]]);

        $this->platformSession($admin)->postJson(route('platform.ai-team.chat', $agent), ['message' => 'keputusan apa aja? sekalian bikin strategi'])
            ->assertOk()->assertJsonPath('assistant.mode', 'ai')
            ->assertJsonPath('assistant.content', 'Satu keputusan nunggu: harga paket tahunan. Strateginya sudah kuajukan, tinggal kamu setujui.');

        Http::assertSent(fn ($r) => collect(data_get($r->data(), 'messages', []))->contains(fn ($m) => ($m['role'] ?? '') === 'tool' && str_contains((string) $m['content'], 'Putuskan harga paket tahunan')));
        $this->assertDatabaseHas('platform_ai_tasks', ['title' => 'Strategi follow-up trial', 'type' => 'chat_strategy', 'status' => 'proposed', 'requires_approval' => true]);
    }

    public function test_agent_nudges_once_about_decisions_waiting_over_a_day(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        $agent = PlatformAiAgent::where('key', 'ads')->firstOrFail();
        $task = $agent->tasks()->create(['type' => 'analysis', 'title' => 'Uji iklan 3 hari', 'summary' => 's', 'risk' => 'high', 'status' => 'proposed', 'requires_approval' => true]);
        $task->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->assertSame(1, app(PlatformAiOperations::class)->nudges());
        $this->assertSame(0, app(PlatformAiOperations::class)->nudges());
        $this->assertDatabaseHas('platform_ai_messages', ['platform_ai_agent_id' => $agent->id, 'role' => 'assistant']);
    }

    private function aiOn(): void
    {
        config(['services.yoka_ai.key' => 'k', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        app(PlatformAiOperations::class)->ensureAgents();
    }

    private function manualTask(string $agentKey = 'chief'): PlatformAiTask
    {
        return PlatformAiAgent::where('key', $agentKey)->firstOrFail()->tasks()->create([
            'type' => 'manual', 'title' => 'Rangkum 15 keputusan terbuka', 'summary' => 'Kumpulkan dan urutkan.',
            'risk' => 'low', 'status' => 'proposed', 'requires_approval' => true,
        ]);
    }

    public function test_approved_task_is_executed_by_its_agent_and_the_report_lands_in_chat(): void
    {
        $this->aiOn();
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Bos, ini hasilnya: ada 2 keputusan, yang paling mendesak harga tahunan.']]]])]);
        $task = $this->manualTask();

        $this->platformSession($this->admin())->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve'])->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('Bos, ini hasilnya: ada 2 keputusan, yang paling mendesak harga tahunan.', data_get($task->result, 'report'));
        $this->assertDatabaseHas('platform_ai_messages', ['platform_ai_agent_id' => $task->platform_ai_agent_id, 'role' => 'assistant', 'content' => data_get($task->result, 'report')]);
    }

    public function test_execution_without_ai_connection_is_honest_and_does_not_fake_completion(): void
    {
        config(['services.yoka_ai.key' => null]);
        app(PlatformAiOperations::class)->ensureAgents();
        Http::fake();
        $task = $this->manualTask();

        $this->platformSession($this->admin())->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve'])->assertRedirect();

        $task->refresh();
        $this->assertSame('approved', $task->status);
        $this->assertNull($task->executed_at);
        $this->assertNotNull($task->error);
        Http::assertNothingSent();
    }

    public function test_replying_setuju_in_chat_approves_the_proposed_task_and_runs_it(): void
    {
        $this->aiOn();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $task = $agent->tasks()->create(['type' => 'chat_task', 'title' => 'Kumpulkan data keputusan', 'summary' => 'Susun daftar.', 'risk' => 'low', 'status' => 'proposed', 'requires_approval' => true]);
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Tugasnya kuajukan, nunggu persetujuanmu.', 'meta' => ['proposed_tasks' => [$task->id]]]);

        Http::fakeSequence('https://ai.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => 'Siap, sudah disetujui. Aku mulai kerjakan sekarang, hasilnya nyusul di sini.']]]])
            ->push(['choices' => [['message' => ['content' => 'Hasil: ada 1 keputusan terbuka.']]]]);

        $this->platformSession($this->admin())->postJson(route('platform.ai-team.chat', $agent), ['message' => 'setuju'])->assertOk();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->approved_at);
        $this->assertSame('Hasil: ada 1 keputusan terbuka.', data_get($task->result, 'report'));
        Http::assertSent(fn ($r) => collect(data_get($r->data(), 'messages', []))->contains(fn ($m) => ($m['role'] ?? '') === 'system' && str_contains((string) $m['content'], 'Hasilnya BELUM ada')));
    }

    public function test_conditional_or_unrelated_replies_never_approve_a_task(): void
    {
        $this->aiOn();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $task = $agent->tasks()->create(['type' => 'chat_task', 'title' => 'Kumpulkan data', 'summary' => 's', 'risk' => 'low', 'status' => 'proposed', 'requires_approval' => true]);
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Nunggu persetujuanmu.', 'meta' => ['proposed_tasks' => [$task->id]]]);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Oke, kuubah dulu.']]]])]);

        $admin = $this->admin();
        foreach (['setuju tapi ubah targetnya dulu', 'kondisi keuangan gimana?', 'ya ampun capek banget hari ini, jangan dulu'] as $text) {
            $this->platformSession($admin)->postJson(route('platform.ai-team.chat', $agent), ['message' => $text])->assertOk();
        }

        $this->assertSame('proposed', $task->fresh()->status);
    }

    public function test_chat_approval_does_not_apply_to_older_proposals(): void
    {
        $this->aiOn();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $task = $agent->tasks()->create(['type' => 'chat_task', 'title' => 'Lama', 'summary' => 's', 'risk' => 'low', 'status' => 'proposed', 'requires_approval' => true]);
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Usul lama.', 'meta' => ['proposed_tasks' => [$task->id]]]);
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Obrolan biasa setelahnya.', 'meta' => ['proposed_tasks' => []]]);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Oke.']]]])]);

        $this->platformSession($this->admin())->postJson(route('platform.ai-team.chat', $agent), ['message' => 'setuju'])->assertOk();

        $this->assertSame('proposed', $task->fresh()->status);
    }

    public function test_agent_can_delegate_a_task_to_a_teammate_and_it_stays_proposed(): void
    {
        $this->aiOn();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $luna = PlatformAiAgent::where('key', 'finance')->firstOrFail();

        Http::fakeSequence('https://ai.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'create_task', 'arguments' => json_encode(['title' => 'Hitung runway', 'summary' => 'Hitung runway 6 bulan.', 'assign_to' => 'finance'])]]]]]]])
            ->push(['choices' => [['message' => ['content' => 'Sudah kuajukan ke Luna, nunggu persetujuanmu dulu.']]]]);

        $this->platformSession($this->admin())->postJson(route('platform.ai-team.chat', $agent), ['message' => 'minta Luna hitung runway'])->assertOk();

        $this->assertDatabaseHas('platform_ai_tasks', ['platform_ai_agent_id' => $luna->id, 'title' => 'Hitung runway', 'status' => 'proposed']);
        $this->assertDatabaseMissing('platform_ai_tasks', ['platform_ai_agent_id' => $agent->id, 'title' => 'Hitung runway']);
        $last = $agent->messages()->where('role', 'assistant')->latest('id')->first();
        $this->assertNotEmpty(data_get($last->meta, 'proposed_tasks'));
    }

    public function test_old_bapak_style_replies_are_not_fed_back_to_the_model(): void
    {
        $this->aiOn();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Baik, Pak. Saya akan segera kirim instruksi ke tim.']);
        $agent->messages()->create(['role' => 'assistant', 'content' => 'Oke Bos, nanti kita bahas.']);
        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Siap.']]]])]);

        $this->platformSession($this->admin())->postJson(route('platform.ai-team.chat', $agent), ['message' => 'gimana?'])->assertOk();

        Http::assertSent(function ($r) {
            $all = json_encode(data_get($r->data(), 'messages', []));

            return ! str_contains($all, 'Baik, Pak.') && str_contains($all, 'Oke Bos, nanti kita bahas.');
        });
    }
}
