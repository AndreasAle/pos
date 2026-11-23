<?php

namespace App\Services;

use App\Http\Controllers\PlatformTelegramWebhookController;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramDiscussionBinding;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use App\Models\PlatformAiTelegramMessage;
use App\Models\PlatformAiTelegramNotification;
use App\Models\PlatformAiTelegramUpdate;
use App\Services\PlatformAgents\AgentIntent;
use App\Services\PlatformAgents\BusinessReasoning;
use App\Services\PlatformAgents\ConversationDecision;
use App\Services\PlatformAgents\ResponseGuard;
use App\Services\PlatformAgents\WorkingMemory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformTelegramConversation
{
    public function __construct(private PlatformTelegramGateway $gateway, private PlatformTelegramRouter $router) {}

    public function process(PlatformAiTelegramUpdate $update): void
    {
        // Klaim atomik: hanya satu proses yang boleh mengerjakan update ini. Dua pemanggilan
        // bersamaan (job after-response, retry, worker antrean) sebelumnya bisa sama-sama lolos
        // cek status lalu menjawab dua kali dengan isi yang berbeda.
        $claimed = PlatformAiTelegramUpdate::whereKey($update->id)->whereIn('status', ['received', 'failed'])
            ->update(['status' => 'processing', 'updated_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $update->refresh();
        try {
            $payload = (array) $update->payload;
            $message = (array) data_get($payload, 'message', []);
            PlatformTelegramImages::$current = [];
            // Foto dikirim Telegram dengan "caption", bukan "text".
            $text = trim((string) (data_get($message, 'text') ?? data_get($message, 'caption', '')));
            $fromId = data_get($message, 'from.id');
            $hasImage = PlatformTelegramImages::hasImage($message);
            if (($text === '' && ! $hasImage) || data_get($message, 'from.is_bot')) {
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
            $threadId = data_get($message, 'message_thread_id');
            // Satu pesan Bos = satu pemrosesan, apa pun jalurnya (jawaban, perintah, absen, meeting).
            // Pesan yang sama bisa masuk sebagai beberapa update (satu per bot di grup).
            // Kunci = pengirim + waktu + isi (bukan message_id: di basic group nomornya beda per bot).
            $messageKey = substr(PlatformTelegramWebhookController::fingerprint((int) $update->telegram_chat_id, $fromId, data_get($message, 'date'), $text, (int) $update->telegram_message_id), 0, 40);
            if (! PlatformAiTelegramNotification::claim('owner_message', $group, $threadId, $messageKey)) {
                app(PlatformAiTelemetry::class)->quality('duplicate_reply_blocked', null, ['stage' => 'message']);
                $this->finish($update, 'duplicate');

                return;
            }
            $group->update(['last_activity_at' => now()]);
            // Foto: unduh sekali, ikut terbaca oleh agen. Foto tanpa teks disimpan untuk pesan berikutnya.
            PlatformTelegramImages::$current = app(PlatformTelegramImages::class)->collect($bot, $group, $identity, $message, (int) $fromId, $text);
            if ($text === '') {
                $this->finish($update, 'photo_stored');

                return;
            }
            PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_admin_id' => $identity->platform_admin_id, 'interaction_id' => $update->interaction_id, 'direction' => 'inbound', 'channel' => $threadId ? 'telegram_topic' : 'telegram_group', 'telegram_message_id' => $update->telegram_message_id, 'thread_id' => $threadId, 'content' => $text, 'delivery_status' => 'received']);
            // Balasan (reply) ke pesan agen: catat pesan yang dirujuk agar konteksnya bisa diselesaikan.
            $replied = $this->repliedMessage($group, $message);
            $route = $this->router->route($text, $group, $bot, $replied?->agent?->key);
            $route['replied_message_id'] = $replied?->id;
            if ($route['kind'] === 'ignore') {
                $this->finish($update, 'ignored');

                return;
            }
            // Konfirmasi pembersihan papan yang sebelumnya diminta Bos di grup ini.
            if ($this->confirmCleanup($text, $group, $update, $threadId)) {
                $this->finish($update);

                return;
            }
            if ($route['kind'] === 'roll_call') {
                app(PlatformTelegramOffice::class)->rollCall($group, $update->interaction_id, $threadId, $identity->admin);
                $this->finish($update);

                return;
            }
            if ($route['kind'] === 'task_cleanup') {
                $this->proposeCleanup($group, $update, $threadId);
                $this->finish($update);

                return;
            }
            if ($route['kind'] === 'command') {
                $this->command($route['command'], $group, $update, $threadId);
                $this->finish($update);

                return;
            }
            if ($route['kind'] === 'chatter') {
                app(PlatformTelegramOffice::class)->runChatter($group, null, true, $route['topic']);
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
        $office = app(PlatformTelegramOffice::class);
        $telemetry = app(PlatformAiTelemetry::class);
        $intent = AgentIntent::classify($route['question']);
        // Keputusan bertipe hanya membentuk jawaban; izin tetap dari $intent['allows_task'].
        $decision = ConversationDecision::decide($route['question'], $intent);
        $decisionFacts = array_merge([$decision->asFact()], BusinessReasoning::facts($decision->domains));
        $agentKeys = array_slice(array_values(array_unique($route['agents'])), 0, 3);

        // "coba kirim di sini dulu" tanpa nama: tujukan ke agen yang terakhir bicara di grup ini.
        if ($intent['reference'] && ! $route['replied_message_id'] && ! $this->namesAgent($route['question'])) {
            $lastSpeaker = PlatformAiTelegramMessage::with('agent')->where('platform_ai_telegram_group_id', $group->id)->where('thread_id', $threadId)
                ->where('direction', 'outbound')->whereNotNull('platform_ai_agent_id')->latest('id')->first()?->agent?->key;
            $agentKeys = $lastSpeaker ? [$lastSpeaker] : $agentKeys;
        }

        $agentsByKey = PlatformAiAgent::whereIn('key', $agentKeys)->where('status', 'active')->get()->keyBy('key');
        $agents = collect($agentKeys)->map(fn (string $key) => $agentsByKey->get($key))->filter();
        $origin = ['group_id' => $group->id, 'admin_id' => $group->platform_admin_id, 'chat_id' => $group->telegram_chat_id, 'thread_id' => $threadId, 'message_id' => $update->telegram_message_id, 'interaction_id' => $update->interaction_id];
        $contextKey = (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : '');
        $spoken = [];
        $consulted = [];

        foreach ($agents as $agent) {
            $targetBot = PlatformAiTelegramBot::where('platform_ai_agent_id', $agent->id)->where('status', 'active')->first();
            if (! $targetBot) {
                continue;
            }
            // Lapis kedua: satu agen menjawab satu pesan Bos tepat sekali, walau pesan yang sama
            // masuk lewat beberapa update (tujuh bot di grup yang sama) atau diproses ulang.
            if (! PlatformAiTelegramNotification::claim('agent_reply', $group, $threadId, $update->interaction_id.':'.$agent->key)) {
                $telemetry->quality('duplicate_reply_blocked', $agent);

                continue;
            }
            $facts = array_merge([WorkingMemory::asFact($agent, $group, $threadId)], $decisionFacts);
            if ($decision->domains !== []) {
                $telemetry->quality('business_check_applied', $agent, ['domains' => implode(',', $decision->domains)]);
            }
            if ($decision->correction) {
                $telemetry->quality('correction_handled', $agent);
            }

            // Rujukan ke hasil sebelumnya: kirim hasil yang benar-benar ada, atau akui belum ada.
            if ($intent['reference']) {
                $resolved = $this->resolveDeliverable($agent, $group, $threadId, $route['replied_message_id']);
                if ($resolved['kind'] === 'completed_task') {
                    $this->gateway->sendHuman($targetBot, $group, $resolved['text'], $update->interaction_id, $threadId, ['task_id' => $resolved['task_id']]);
                    $telemetry->quality('reference_resolved', $agent, ['kind' => 'completed_task']);
                    $spoken[] = $agent->key;

                    continue;
                }
                $facts[] = $resolved['fact'];
                $telemetry->quality($resolved['kind'] === 'none' ? 'reference_unresolved' : 'reference_resolved', $agent, ['kind' => $resolved['kind']]);
                if ($resolved['kind'] === 'pending_task') {
                    $telemetry->quality('deliverable_missing', $agent);
                }
            }

            // Kolaborasi nyata: rekan yang ditanya benar-benar dijalankan dan jawabannya tersimpan.
            $consultKey = $intent['consult'];
            if ($consultKey && $consultKey !== $agent->key && ! in_array($consultKey, $consulted, true)) {
                $answer = $office->consult($group, $agent, $consultKey, $route['question'], $update->interaction_id, $threadId, $identity->admin);
                if ($answer) {
                    $consulted[] = $consultKey;
                    $facts[] = "Kamu barusan benar-benar bertanya ke {$answer['name']} dan jawabannya sudah terkirim di grup: \"".Str::limit($answer['content'], 600).'". Pakai jawaban itu untuk rekomendasi akhirmu.';
                    $telemetry->quality('consultation', $agent, ['with' => $consultKey]);
                }
            }

            $history = $office->history($group, $agent, $threadId, $update->interaction_id);
            $question = $route['question'];
            if ($agents->count() > 1) {
                $question .= "\n\n[Konteks sistem: Ini diskusi grup kerja. Baca pendapat rekan yang sudah muncul di riwayat. Tambahkan sudut pandang bidangmu, tanggapi bila perlu, dan jangan mengulang isi mereka.]";
            }
            $policy = ['allow_tasks' => $intent['allows_task'], 'origin' => $origin];
            $result = app(PlatformAiOperations::class)->reply($agent, $question, [], $facts, $identity->admin, 'telegram_chat', $history, $contextKey, $policy);
            $ids = (array) ($result['created_tasks'] ?? []);

            $spokeNames = PlatformAiAgent::whereIn('id', PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)
                ->where('direction', 'outbound')->where('created_at', '>=', now()->subHours(2))->whereNotNull('platform_ai_agent_id')
                ->distinct()->pluck('platform_ai_agent_id'))->pluck('name')->all();
            $guarded = ResponseGuard::validate($result['content'], [
                'tools_used' => (array) ($result['tools_used'] ?? []), 'created_tasks' => $ids, 'spoke_agents' => $spokeNames,
            ]);
            foreach ($guarded['violations'] as $violation) {
                $metric = match (true) {
                    $violation === 'filler' => 'filler_removed',
                    str_starts_with($violation, 'terminology') => 'terminology_corrected',
                    default => 'unsupported_claim_removed',
                };
                $telemetry->quality($metric, $agent, ['type' => $violation]);
            }
            $content = $guarded['text'] !== '' ? $guarded['text'] : 'iya bos?';
            // Hasil kerja (hitungan, draf, ide, analisis) boleh utuh; obrolan biasa dipaksa pendek.
            $text = $intent['deliverable']
                ? PlatformTelegramStyle::clean($content)
                : PlatformTelegramStyle::chatLimit($office->humanize($agent, $content, $route['question']));

            if ($ids !== []) {
                $telemetry->quality('task_created_explicit', $agent, ['count' => count($ids)]);
                if ($notice = app(PlatformTelegramNotifications::class)->proposalNotice($group, $threadId, $ids)) {
                    $text .= "\n\n".$notice;
                }
            } elseif (! $intent['allows_task']) {
                $telemetry->quality(($result['mode'] ?? '') === 'ai' ? 'direct_answer' : 'direct_answer_failed', $agent, ['intent' => $intent['intent']]);
            }
            $this->gateway->sendHuman($targetBot, $group, $text, $update->interaction_id, $threadId, ['task_id' => ($ids[0] ?? null)]);
            $spoken[] = $agent->key;
            if (($result['mode'] ?? '') === 'ai') {
                $telemetry->quality('useful_interaction', $agent);
            }
            app(PlatformAiContext::class)->reviseConversationState($agent, $identity->admin, $route['question'], $ids, $content, 'telegram_group', $contextKey);
        }
        if ($spoken !== [] && filled(config('services.yoka_ai.key')) && ! $intent['reference']) {
            $office->maybeChimeIn($group, $route['question'], array_merge($spoken, $consulted), $update->interaction_id, $threadId);
        }
    }

    /**
     * Langkah 1 pembersihan papan: Ramli menyebut tugas yang akan dibatalkan dan minta konfirmasi.
     * Hanya tugas yang masih menunggu approve; tugas yang sedang/sudah dikerjakan tidak disentuh.
     */
    private function proposeCleanup(PlatformAiTelegramGroup $group, PlatformAiTelegramUpdate $update, ?int $threadId): void
    {
        $chief = $this->chiefBot($update);
        $tasks = PlatformAiTask::with('agent:id,name')->where('status', 'proposed')->latest('id')->get(['id', 'title', 'platform_ai_agent_id']);
        if ($tasks->isEmpty()) {
            $this->gateway->send($chief, $group, 'papan udah bersih bos, gak ada tugas yang nunggu approve.', $update->interaction_id, $threadId);

            return;
        }
        Cache::put($this->cleanupKey($group, $threadId), ['ids' => $tasks->pluck('id')->all(), 'by' => $update->telegram_message_id], now()->addMinutes(10));
        $list = $tasks->take(8)->map(fn ($t) => '- '.Str::limit($t->title, 70).' ('.$t->agent?->name.')')->join("\n");
        $more = $tasks->count() > 8 ? "\n…dan ".($tasks->count() - 8).' lainnya' : '';
        $this->gateway->send($chief, $group, "ada {$tasks->count()} tugas yang nunggu approve:\n{$list}{$more}\n\nbalas \"ya batalkan\" dalam 10 menit buat batalin semuanya. tugasnya diarsipkan (status ditolak), bukan dihapus permanen. yang lagi jalan gak kusentuh.", $update->interaction_id, $threadId);
    }

    /** Langkah 2: hanya balasan konfirmasi yang jelas, dari grup yang sama, dalam 10 menit. */
    private function confirmCleanup(string $text, PlatformAiTelegramGroup $group, PlatformAiTelegramUpdate $update, ?int $threadId): bool
    {
        $pending = Cache::get($this->cleanupKey($group, $threadId));
        if (! $pending || ! preg_match('/^(ya|iya|yes|ok|oke|gas)\b.{0,6}\b(batal(kan|in)?|hapus|bersihin)\b/iu', trim($text))) {
            return false;
        }
        Cache::forget($this->cleanupKey($group, $threadId));
        $tasks = PlatformAiTask::whereIn('id', (array) $pending['ids'])->where('status', 'proposed')->get();
        foreach ($tasks as $task) {
            $task->update(['status' => 'rejected', 'error' => 'Dibatalkan pemilik lewat Telegram Office.']);
        }
        PlatformActivityLog::record('ai.task.bulk_reject', "Membatalkan {$tasks->count()} tugas tertunda lewat Telegram Office.");
        $this->gateway->send($this->chiefBot($update), $group, "beres bos, {$tasks->count()} tugas udah dibatalin. masih bisa dilihat di arsip Operations Center kalau perlu.", $update->interaction_id, $threadId);

        return true;
    }

    private function cleanupKey(PlatformAiTelegramGroup $group, ?int $threadId): string
    {
        return "telegram-office:cleanup:{$group->id}:".($threadId ?? 0);
    }

    private function chiefBot(PlatformAiTelegramUpdate $update): PlatformAiTelegramBot
    {
        return PlatformAiTelegramBot::whereHas('agent', fn ($q) => $q->where('key', 'chief'))->where('status', 'active')->first() ?: $update->bot;
    }

    /** Pesan agen yang di-reply Bos, hanya dari grup yang sama. */
    private function repliedMessage(PlatformAiTelegramGroup $group, array $message): ?PlatformAiTelegramMessage
    {
        $repliedId = data_get($message, 'reply_to_message.message_id');
        if (! $repliedId) {
            return null;
        }
        $base = fn () => PlatformAiTelegramMessage::with('agent')->where('platform_ai_telegram_group_id', $group->id)->where('direction', 'outbound');
        // Di basic group nomor pesan berbeda per bot: cocokkan lewat bot pengirim + waktu kirim.
        $botUserId = data_get($message, 'reply_to_message.from.id');
        $sentAt = data_get($message, 'reply_to_message.date');
        if ($botUserId && $sentAt) {
            $botId = PlatformAiTelegramBot::where('telegram_bot_id', (int) $botUserId)->value('id');
            if ($botId) {
                $at = Carbon::createFromTimestamp((int) $sentAt);
                $match = $base()->where('platform_ai_telegram_bot_id', $botId)
                    ->whereBetween('delivered_at', [$at->copy()->subSeconds(5), $at->copy()->addSeconds(5)])->latest('id')->first()
                    ?? $base()->where('platform_ai_telegram_bot_id', $botId)->where('telegram_message_id', (int) $repliedId)->latest('id')->first();
                if ($match) {
                    return $match;
                }
            }
        }

        return $base()->where('telegram_message_id', (int) $repliedId)->latest('id')->first();
    }

    private function namesAgent(string $text): bool
    {
        return (bool) preg_match('/\b('.PlatformTelegramRouter::CALL_NAMES.')\b/iu', $text);
    }

    /**
     * Cari "hasil" yang dirujuk Bos, berurutan dari bukti terkuat:
     *  1. tugas agen ini (dari grup ini) yang SUDAH selesai -> kirim hasil aslinya
     *  2. tugas yang baru diusulkan/di-approve tapi belum ada hasil -> akui, lalu kerjakan sekarang
     *  3. pesan agen yang di-reply / pesan terakhirnya di grup -> jadikan konteks untuk diulang lengkap
     *
     * @return array{kind:string, text?:string, task_id?:int, fact?:string}
     */
    private function resolveDeliverable(PlatformAiAgent $agent, PlatformAiTelegramGroup $group, ?int $threadId, ?int $repliedMessageId): array
    {
        $tasks = PlatformAiTask::where('platform_ai_agent_id', $agent->id)->where('created_at', '>=', now()->subDays(2))
            ->where('payload->telegram_origin->group_id', $group->id)->latest('id')->limit(5)->get();
        $replied = $repliedMessageId ? PlatformAiTelegramMessage::find($repliedMessageId) : null;
        if ($replied?->platform_ai_task_id) {
            $tasks = $tasks->prepend(PlatformAiTask::find($replied->platform_ai_task_id))->filter()->unique('id');
        }
        $completed = $tasks->first(fn ($t) => $t->status === 'completed' && filled(data_get($t->result, 'report')));
        if ($completed) {
            return ['kind' => 'completed_task', 'task_id' => $completed->id, 'text' => "ini hasil \"{$completed->title}\":\n\n".PlatformTelegramStyle::clean((string) data_get($completed->result, 'report'))];
        }
        $pending = $tasks->first(fn ($t) => in_array($t->status, ['proposed', 'approved'], true));
        if ($pending) {
            $state = $pending->status === 'proposed' ? 'masih menunggu approve' : 'sudah di-approve tapi belum selesai dikerjakan sistem';

            return ['kind' => 'pending_task', 'fact' => "RUJUKAN: Bos minta hasil yang tadi. Hasil jadinya BELUM ADA: tugas \"{$pending->title}\" {$state}. Jangan kirim judul/deskripsi tugas seolah itu hasil. Bilang singkat hasilnya belum ada, lalu kerjakan analisisnya LANGSUNG di pesan ini (pakai tool bila perlu) karena ini kerja baca-data yang aman."];
        }
        $source = $replied ?? PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->where('thread_id', $threadId)
            ->where('platform_ai_agent_id', $agent->id)->where('direction', 'outbound')->latest('id')->first();
        if ($source) {
            return ['kind' => 'previous_message', 'fact' => 'RUJUKAN: Bos merujuk pesanmu sebelumnya: "'.Str::limit($source->content, 800).'". Kalau isinya baru janji/rencana, kerjakan hasil lengkapnya sekarang. Kalau sudah berisi hasil, tulis ulang versi lengkapnya di sini.'];
        }

        return ['kind' => 'none', 'fact' => 'RUJUKAN: Bos merujuk sesuatu "yang tadi" tapi tidak ada catatan hasil atau pesanmu yang cocok. Ajukan SATU pertanyaan singkat: yang mana maksudnya.'];
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
                $this->gateway->sendHuman($targetBot, $group, $contribution->content, $update->interaction_id, $threadId, ['discussion_id' => $discussion->id]);
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
            'help' => 'Perintah: /ask nama pertanyaan, /ngobrol topik, /meeting topik, /tasks, /status, /team, /briefing. Approval sensitif tetap dilakukan di dashboard.',
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
