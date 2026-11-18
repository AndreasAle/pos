<?php

namespace App\Services;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramDiscussionBinding;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use App\Models\PlatformAiTelegramMessage;
use App\Models\PlatformAiTelegramUpdate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformTelegramConversation
{
    public function __construct(private PlatformTelegramGateway $gateway, private PlatformTelegramRouter $router) {}

    public function process(PlatformAiTelegramUpdate $update): void
    {
        $update->update(['status' => 'processing']);
        try {
            $payload = (array) $update->payload;
            $message = (array) data_get($payload, 'message', []);
            $text = trim((string) data_get($message, 'text', ''));
            $fromId = data_get($message, 'from.id');
            if ($text === '' || data_get($message, 'from.is_bot')) {
                $this->finish($update, 'ignored');

                return;
            }
            $bot = $update->bot()->with('agent')->firstOrFail();
            if (preg_match('/^\/link\s+([A-Z0-9-]{6,20})$/i', $text, $m)) {
                $this->link($update, $bot, (int) $fromId, $m[1], data_get($message, 'from.username'));

                return;
            }
            $identity = PlatformAiTelegramIdentity::with('admin')->where('telegram_user_id', $fromId)->where('status', 'verified')->first();
            if (! $identity) {
                $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Akses belum terhubung. Buat kode tautan dari Yoka Dashboard terlebih dahulu.');
                $this->finish($update, 'unauthorized');

                return;
            }
            if (preg_match('/^\/connect\b/i', $text)) {
                $this->connectGroup($update, $bot, $identity);

                return;
            }
            $group = PlatformAiTelegramGroup::where('telegram_chat_id', $update->telegram_chat_id)->where('enabled', true)->first();
            if (! $group || $group->platform_admin_id !== $identity->platform_admin_id) {
                $this->finish($update, 'unauthorized');

                return;
            }
            $group->update(['last_activity_at' => now()]);
            $threadId = data_get($message, 'message_thread_id');
            PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_admin_id' => $identity->platform_admin_id, 'interaction_id' => $update->interaction_id, 'direction' => 'inbound', 'channel' => $threadId ? 'telegram_topic' : 'telegram_group', 'telegram_message_id' => $update->telegram_message_id, 'thread_id' => $threadId, 'content' => $text, 'delivery_status' => 'received']);
            $route = $this->router->route($text, $group, $bot);
            if ($route['kind'] === 'ignore') {
                $this->finish($update, 'ignored');

                return;
            }
            if ($route['kind'] === 'command') {
                $this->command($route['command'], $group, $update, $threadId);
                $this->finish($update);

                return;
            }
            if ($route['kind'] === 'meeting') {
                $this->meeting($route, $identity, $group, $update, $threadId);
                $this->finish($update);

                return;
            }
            $this->respond($route, $identity, $group, $update, $threadId);
            $this->finish($update);
        } catch (\Throwable $e) {
            $update->update(['status' => 'failed', 'error' => class_basename($e).': '.Str::limit($e->getMessage(), 300), 'processed_at' => now()]);
            throw $e;
        }
    }

    private function respond(array $route, PlatformAiTelegramIdentity $identity, PlatformAiTelegramGroup $group, PlatformAiTelegramUpdate $update, ?int $threadId): void
    {
        $agentKeys = array_slice(array_values(array_unique($route['agents'])), 0, 3);
        $agentsByKey = PlatformAiAgent::whereIn('key', $agentKeys)->where('status', 'active')->get()->keyBy('key');
        $agents = collect($agentKeys)->map(fn (string $key) => $agentsByKey->get($key))->filter();
        foreach ($agents as $agent) {
            $history = PlatformAiTelegramMessage::with('agent')
                ->where('platform_ai_telegram_group_id', $group->id)
                ->where('thread_id', $threadId)
                ->where(fn ($query) => $query->where('interaction_id', '!=', $update->interaction_id)->orWhere('direction', 'outbound'))
                ->latest()->limit(12)->get()->reverse()
                ->map(function (PlatformAiTelegramMessage $message) {
                    if ($message->direction === 'outbound') {
                        $speaker = $message->agent?->name ?? 'Rekan tim';

                        return ['role' => 'assistant', 'content' => "[{$speaker}]: {$message->content}"];
                    }

                    return ['role' => 'user', 'content' => "[Bos]: {$message->content}"];
                })->values()->all();
            $question = $route['question'];
            if ($agents->count() > 1) {
                $question .= "\n\n[Konteks sistem: Ini diskusi grup kerja. Baca pendapat rekan yang sudah muncul di riwayat. Tambahkan sudut pandang bidangmu, tanggapi bila perlu, dan jangan mengulang isi mereka.]";
            }
            $result = app(PlatformAiOperations::class)->reply($agent, $question, [], [], $identity->admin, 'telegram_chat', $history, (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : ''));
            $targetBot = PlatformAiTelegramBot::where('platform_ai_agent_id', $agent->id)->where('status', 'active')->first();
            if (! $targetBot) {
                continue;
            }
            $text = Str::limit($result['content'], 3500, '…');
            if ($ids = ($result['created_tasks'] ?? [])) {
                $text .= "\n\nTugas masuk Operations Center dan tetap menunggu approval: ".url('/platform/ai-team').'?tab=tasks';
            }
            $this->gateway->send($targetBot, $group, $text, $update->interaction_id, $threadId, ['task_id' => ($ids[0] ?? null)]);
            app(PlatformAiContext::class)->reviseConversationState($agent, $identity->admin, $route['question'], $ids ?? [], $result['content'], 'telegram_group', (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : ''));
        }
    }

    private function meeting(array $route, PlatformAiTelegramIdentity $identity, PlatformAiTelegramGroup $group, PlatformAiTelegramUpdate $update, ?int $threadId): void
    {
        $agents = PlatformAiAgent::whereIn('key', array_slice($route['agents'], 0, 4))->where('status', 'active')->get();
        if ($agents->count() < 2) {
            $agents = PlatformAiAgent::whereIn('key', ['chief', 'marketing'])->where('status', 'active')->get();
        }
        $discussion = PlatformAiDiscussion::create(['created_by' => $identity->platform_admin_id, 'chair_agent_id' => $agents->firstWhere('key', 'chief')?->id, 'objective' => $route['objective'], 'status' => 'pending', 'limits' => ['channel' => 'telegram', 'max_agents' => 4, 'max_rounds' => 1]]);
        PlatformAiTelegramDiscussionBinding::create(['platform_ai_discussion_id' => $discussion->id, 'platform_ai_telegram_group_id' => $group->id, 'interaction_id' => $update->interaction_id, 'trigger_message_id' => $update->telegram_message_id, 'thread_id' => $threadId, 'participant_ids' => $agents->pluck('id')->all(), 'status' => 'running']);
        app(PlatformAiDiscussions::class)->run($discussion, $identity->admin, $agents->pluck('id')->all());
        foreach ($discussion->fresh('contributions.agent')->contributions as $contribution) {
            $targetBot = PlatformAiTelegramBot::where('platform_ai_agent_id', $contribution->platform_ai_agent_id)->where('status', 'active')->first();
            if ($targetBot) {
                $this->gateway->send($targetBot, $group, $contribution->content, $update->interaction_id, $threadId, ['discussion_id' => $discussion->id]);
            }
        }
        $chief = PlatformAiTelegramBot::whereHas('agent', fn ($q) => $q->where('key', 'chief'))->where('status', 'active')->first();
        if ($chief) {
            $this->gateway->send($chief, $group, "Ringkasan meeting:\n".Str::limit($discussion->fresh()->synthesis, 3000, '…')."\n\nDetail: ".url('/platform/ai-team'), $update->interaction_id, $threadId, ['discussion_id' => $discussion->id]);
        }
        PlatformAiTelegramDiscussionBinding::where('platform_ai_discussion_id', $discussion->id)->update(['status' => $discussion->fresh()->status]);
    }

    private function command(string $command, PlatformAiTelegramGroup $group, PlatformAiTelegramUpdate $update, ?int $threadId): void
    {
        $chief = PlatformAiTelegramBot::whereHas('agent', fn ($q) => $q->where('key', 'chief'))->where('status', 'active')->first() ?: $update->bot;
        $text = match ($command) {
            'help' => 'Perintah: /ask nama pertanyaan, /meeting topik, /tasks, /status, /team, /briefing. Approval sensitif tetap dilakukan di dashboard.',
            'tasks' => PlatformAiTask::with('agent')->whereIn('status', ['proposed', 'approved'])->latest()->limit(8)->get()->map(fn ($t) => "• {$t->title} — {$t->agent?->name} ({$t->status})")->prepend('Pekerjaan aktif:')->join("\n"),
            'team' => PlatformAiAgent::orderBy('id')->get()->map(fn ($a) => "• {$a->name} — {$a->status}")->prepend('Tim AI:')->join("\n"),
            'status' => "Telegram Office aktif. Mode grup: {$group->mode}. Approval sensitif tetap di Operations Center.",
            'briefing' => $this->briefing(), default => 'Perintah tidak dikenali.'
        };
        $this->gateway->send($chief, $group, $text, $update->interaction_id, $threadId);
    }

    private function briefing(): string
    {
        $pending = PlatformAiTask::where('status', 'proposed')->count();
        $running = PlatformAiTask::where('status', 'approved')->count();
        $done = PlatformAiTask::where('status', 'completed')->whereDate('updated_at', today())->count();

        return "Briefing hari ini:\n• {$pending} menunggu keputusan\n• {$running} sedang berjalan\n• {$done} selesai hari ini\n\nTidak ada angka bisnis lain yang ditampilkan tanpa sumber data aktual.";
    }

    private function link(PlatformAiTelegramUpdate $update, PlatformAiTelegramBot $bot, int $userId, string $code, ?string $username): void
    {
        $identity = PlatformAiTelegramIdentity::where('status', 'pending')->where('link_expires_at', '>', now())->get()->first(fn ($item) => Hash::check(Str::upper($code), $item->link_code_hash));
        if (! $identity) {
            $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Kode tautan tidak valid atau sudah kedaluwarsa.');
            $this->finish($update, 'rejected');

            return;
        }
        if (PlatformAiTelegramIdentity::where('telegram_user_id', $userId)->whereKeyNot($identity->id)->exists()) {
            $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Akun Telegram ini sudah terhubung.');
            $this->finish($update, 'rejected');

            return;
        }
        $identity->update(['telegram_user_id' => $userId, 'telegram_username' => $username, 'status' => 'verified', 'verified_at' => now(), 'link_code_hash' => null, 'link_expires_at' => null]);
        $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Akun berhasil terhubung. Tambahkan tujuh bot ke grup privat lalu kirim /connect dari grup tersebut.');
        $this->finish($update);
    }

    private function connectGroup(PlatformAiTelegramUpdate $update, PlatformAiTelegramBot $bot, PlatformAiTelegramIdentity $identity): void
    {
        $type = data_get($update->payload, 'message.chat.type');
        if (! in_array($type, ['group', 'supergroup'], true)) {
            $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Perintah /connect harus dikirim dari grup privat.');
            $this->finish($update, 'rejected');

            return;
        }
        PlatformAiTelegramGroup::updateOrCreate(['telegram_chat_id' => $update->telegram_chat_id], ['platform_admin_id' => $identity->platform_admin_id, 'title' => data_get($update->payload, 'message.chat.title'), 'enabled' => true, 'mode' => 'smart_discussion', 'notification_settings' => ['daily_briefing' => false, 'alerts' => true, 'quiet_start' => '22:00', 'quiet_end' => '07:00', 'timezone' => 'Asia/Jakarta', 'max_alerts_per_day' => 5, 'severity' => 'medium']]);
        $this->gateway->sendRaw($bot, (string) $update->telegram_chat_id, 'Grup berhasil dijadikan Telegram Office. Langsung ngobrol seperti biasa; Ramli akan mengarahkan agen yang paling relevan.');
        $this->finish($update);
    }

    private function finish(PlatformAiTelegramUpdate $update, string $status = 'processed'): void
    {
        $update->update(['status' => $status, 'processed_at' => now(), 'error' => null]);
    }
}
