<?php

namespace App\Services;

use App\Jobs\RunPlatformAiTask;
use App\Models\BusinessSubscription;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiMessage;
use App\Models\PlatformAiRecord;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTaskExecution;
use App\Services\PlatformAgents\AgentBrain;
use App\Services\PlatformAgents\TrialAgent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlatformAiOperations
{
    public function __construct(private PlatformAiAttachmentService $attachmentService) {}

    public const DEFINITIONS = [
        'chief' => ['Ramli', 'Chief of Staff', 'Menyatukan pekerjaan seluruh agen menjadi prioritas bisnis harian.', 'auto_internal'],
        'marketing' => ['Yusuf', 'Marketing Intelligence', 'Menganalisis konten dan menemukan topik serta video yang layak diuji.', 'recommend'],
        'ads' => ['Yono Bakri', 'Ads Manager', 'Menyusun eksperimen iklan dengan batas anggaran dan menghentikan pemborosan.', 'approval'],
        'finance' => ['Luna', 'Finance Strategist', 'Mengukur margin, CAC, LTV, runway, dan memberi simulasi keputusan.', 'recommend'],
        'accounting' => ['Nanda', 'Accounting', 'Merekonsiliasi pendapatan, biaya, dan kelengkapan pencatatan.', 'auto_internal'],
        'cs' => ['Adit', 'Customer Service', 'Menjawab, menindaklanjuti, dan mengeskalasi percakapan pelanggan.', 'approval'],
        'sales' => ['Yosef', 'Sales Development', 'Menilai prospek, menyiapkan pendekatan personal, dan menjaga pipeline.', 'approval'],
    ];

    public const PROFILES = [
        'chief' => ['title' => 'Chief of Staff', 'initials' => 'RA', 'avatar' => 'images/ai-team/ramli.jpg', 'accent' => 'emerald', 'voice' => 'Ramli, partner strategi yang tenang, tegas, melihat gambaran besar, dan berani menyebut satu prioritas utama', 'prompts' => ['Apa prioritas bisnis hari ini?', 'Keputusan apa yang tertunda?', 'Ringkas kondisi semua tim']],
        'marketing' => ['title' => 'Marketing Intelligence', 'initials' => 'YU', 'avatar' => 'images/ai-team/yusuf.jpg', 'accent' => 'cyan', 'voice' => 'Yusuf, rekan kreatif yang antusias tetapi tetap berbasis data, suka membahas hook, audiens, dan ide yang konkret', 'prompts' => ['Konten mana paling potensial?', 'Ide konten berikutnya apa?', 'Apa pola dari konten terbaik?']],
        'ads' => ['title' => 'Ads Manager', 'initials' => 'YB', 'avatar' => 'images/ai-team/yono-bakri.jpg', 'accent' => 'sky', 'voice' => 'Yono Bakri, media buyer yang lugas, hati-hati memakai uang, dan selalu bicara hipotesis, batas rugi, serta ukuran keberhasilan', 'prompts' => ['Konten mana layak diiklankan?', 'Berapa anggaran uji aman?', 'Apa syarat campaign siap?']],
        'finance' => ['title' => 'Finance Strategist', 'initials' => 'LU', 'avatar' => 'images/ai-team/luna.jpg', 'accent' => 'amber', 'voice' => 'Luna, partner keuangan yang membumi, terus terang soal risiko, dan menjelaskan angka dengan bahasa sederhana', 'prompts' => ['Bagaimana margin bulan ini?', 'Berapa batas belanja aman?', 'Apa risiko keuangan terbesar?']],
        'accounting' => ['title' => 'Accounting', 'initials' => 'NA', 'avatar' => 'images/ai-team/nanda.jpg', 'accent' => 'teal', 'voice' => 'Nanda, rekan pembukuan yang teliti, rapi, tidak menghakimi, dan langsung menunjukkan data mana yang perlu dibereskan', 'prompts' => ['Apa data yang belum lengkap?', 'Perlu rekonsiliasi apa?', 'Ringkas pencatatan bulan ini']],
        'cs' => ['title' => 'Customer Service', 'initials' => 'AD', 'avatar' => 'images/ai-team/adit.jpg', 'accent' => 'rose', 'voice' => 'Adit, rekan customer service yang hangat, sabar, peka pada nada pelanggan, tetapi tegas saat perlu eskalasi', 'prompts' => ['Ada tiket yang mendesak?', 'Buat draf balasan pelanggan', 'Apa yang perlu dieskalasi?']],
        'sales' => ['title' => 'Sales Development', 'initials' => 'YO', 'avatar' => 'images/ai-team/yosef.jpg', 'accent' => 'violet', 'voice' => 'Yosef, rekan sales yang aktif dan percaya diri tanpa memaksa, fokus pada kebutuhan prospek dan langkah follow-up berikutnya', 'prompts' => ['Lead mana harus dihubungi?', 'Buat pendekatan untuk lead terbaik', 'Bagaimana kondisi pipeline?']],
    ];

    public function ensureAgents(): void
    {
        foreach (self::DEFINITIONS as $key => [$name, $department, $mission, $autonomy]) {
            $agent = PlatformAiAgent::firstOrCreate(['key' => $key], [
                'name' => $name,
                'department' => $department,
                'mission' => $mission,
                'autonomy' => $autonomy,
                'permissions' => $this->permissions($key),
                'settings' => ['frequency' => $key === 'chief' ? 'daily' : 'weekly'],
                'next_run_at' => now(),
            ]);
            $agent->fill(compact('name', 'department', 'mission', 'autonomy'));
            $agent->permissions = $this->permissions($key);
            $agent->save();
        }
    }

    public function reply(PlatformAiAgent $agent, string $question, array $attachments = [], array $facts = [], ?PlatformAdmin $admin = null, string $operation = 'chat', ?array $historyOverride = null, string $contextKey = 'default'): array
    {
        $snapshot = $this->snapshot($agent);
        $history = $historyOverride ?? $agent->messages()->latest()->limit(12)->get()->reverse()->map(fn (PlatformAiMessage $message) => [
            'role' => $message->role === 'assistant' ? 'assistant' : 'user',
            'content' => $message->content,
        ])->reject(fn ($m) => $m['role'] === 'assistant' && preg_match('/\b(Bapak|Pak)\b/u', $m['content']))->values()->all();

        if (! filled(config('services.yoka_ai.key'))) {
            return [
                'content' => $this->offlineReply($agent, $question, $snapshot, $attachments, $facts),
                'mode' => 'internal',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }

        if ($agent->status !== 'active') {
            return [
                'content' => "Bos, status kerjaku lagi dijeda. Chat kita tetap aman, tapi aku belum menganalisis hal baru sampai kamu mengaktifkanku lagi.\n\n".$this->offlineReply($agent, $question, $snapshot, $attachments, $facts),
                'mode' => 'internal',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }

        $budgetKey = 'platform-ai-chat:'.now()->format('Y-m-d');
        $requestCount = Cache::increment($budgetKey);
        Cache::put($budgetKey, $requestCount, now()->endOfDay());
        if ($requestCount > (int) config('services.yoka_ai.daily_request_budget', 250)) {
            return [
                'content' => "Jatah analisis AI hari ini sudah penuh, Bos. Biar biaya tetap terkendali, sementara aku jawab dari data internal dulu.\n\n".$this->offlineReply($agent, $question, $snapshot, $attachments, $facts),
                'mode' => 'internal',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }

        try {
            $userContent = [[
                'type' => 'text',
                'text' => $question,
            ]];
            foreach ($attachments as $attachment) {
                array_push($userContent, ...$this->attachmentService->modelParts($attachment));
            }
            $brain = app(AgentBrain::class);
            $channel = str_starts_with($operation, 'telegram') ? 'telegram_group' : 'platform_dashboard';
            $context = $admin ? app(PlatformAiContext::class)->package($agent, $admin, $question, $channel, $contextKey) : [];
            $content = $brain->chat($agent, $history, $userContent, $facts, $context, $operation);

            return ['content' => $content, 'mode' => 'ai', 'snapshot' => $snapshot, 'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [], 'created_tasks' => $brain->createdTaskIds];
        } catch (\Throwable $e) {
            Log::warning('Platform AI chat failed', ['agent' => $agent->key, 'exception' => class_basename($e)]);

            return [
                'content' => "Koneksi analisisku lagi bermasalah, Bos. Aku tetap bantu dari data internal yang sudah masuk.\n\n".$this->offlineReply($agent, $question, $snapshot, $attachments, $facts),
                'mode' => 'fallback',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }
    }

    public function snapshot(PlatformAiAgent $agent): array
    {
        $recordsByKind = PlatformAiRecord::selectRaw('kind, count(*) total')->groupBy('kind')->pluck('total', 'kind')->all();
        $taskCounts = $agent->tasks()->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status')->all();
        $latest = $agent->tasks()->latest()->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'agent_status' => $agent->status,
            'open_tasks' => (int) (($taskCounts['proposed'] ?? 0) + ($taskCounts['approved'] ?? 0)),
            'completed_tasks' => (int) ($taskCounts['completed'] ?? 0),
            'records' => $recordsByKind,
            'latest_analysis' => $latest ? ['title' => $latest->title, 'summary' => $latest->summary, 'recommendation' => $latest->recommendation, 'status' => $latest->status] : null,
            'daily_budget' => (int) $agent->daily_budget,
            'permissions' => $agent->permissions ?? [],
            'ai_connected' => filled(config('services.yoka_ai.key')),
            'external_connection' => $this->externalConnectionReady($agent->key),
        ];
    }

    public function externalConnectionReady(string $key): ?bool
    {
        return match ($key) {
            'marketing', 'ads' => (filled(config('services.meta_marketing.access_token')) && filled(config('services.meta_marketing.ad_account_id'))) || (filled(config('services.tiktok_marketing.access_token')) && filled(config('services.tiktok_marketing.advertiser_id'))),
            'cs', 'sales' => filled(config('services.whatsapp_business.access_token')) && filled(config('services.whatsapp_business.phone_number_id')),
            default => null,
        };
    }

    private function offlineReply(PlatformAiAgent $agent, string $question, array $snapshot, array $attachments = [], array $facts = []): string
    {
        $answer = match ($agent->key) {
            'chief' => 'Oke Bos, aku cek dulu dari data yang ada.',
            'marketing' => 'Oke Bos, aku lihat dulu bahan kontennya.',
            'ads' => 'Oke Bos. Soal iklan, aku cek kesiapan dulu sebelum bicara anggaran.',
            'finance' => 'Oke Bos, aku jawab dari angka yang sudah tercatat.',
            'accounting' => 'Oke Bos, aku cek dari catatan yang sudah masuk.',
            'cs' => 'Oke Bos, aku lihat dulu kondisi tiket dan trial.',
            'sales' => 'Oke Bos, aku lihat dulu pipeline-nya.',
            default => 'Oke Bos, aku jawab dari data yang ada dulu.',
        };
        if ($attachments) {
            $answer .= ' File '.collect($attachments)->pluck('original_name')->join(', ').' sudah masuk.';
        }

        $open = PlatformAiTask::with('agent:id,name')->whereIn('status', ['proposed', 'approved'])->latest()->limit(5)->get();
        $records = collect($snapshot['records'])->map(fn ($total, $kind) => "{$total} {$kind}")->join(', ');
        $answer .= '

Yang kelihatan sekarang: '.($records ?: 'belum ada catatan operasional').'.';
        if ($open->isNotEmpty()) {
            $answer .= ' Keputusan yang masih menunggu: '.$open->map(fn ($t) => "{$t->title} ({$t->agent?->name})")->join('; ').'.';
        }
        $latest = $snapshot['latest_analysis'];
        if ($latest) {
            $answer .= "

Temuan terakhirku: {$latest['summary']}";
            if ($latest['recommendation']) {
                $answer .= " Menurutku, {$latest['recommendation']}";
            }
        }
        if ($facts) {
            $answer .= "\n\n".implode(' ', $facts);
        }
        $answer .= '

(Mode terbatas: koneksi AI sedang tidak aktif, jadi aku belum bisa diajak berdiskusi lebih dalam.)';

        return $answer;
    }

    /** Tugas yang boleh dikerjakan sendiri oleh agen: murni baca data dan tulis hasil di dalam Yoka. */
    public const EXECUTABLE_TYPES = ['chat_task', 'chat_decision', 'chat_strategy', 'manual'];

    public function canExecute(PlatformAiTask $task): bool
    {
        return in_array($task->type, self::EXECUTABLE_TYPES, true) && $task->status === 'approved' && $task->executed_at === null;
    }

    /** Dijalankan setelah respons terkirim, supaya halaman tidak menunggu agen bekerja. */
    public function dispatchExecution(PlatformAiTask $task): void
    {
        if (! $this->canExecute($task)) {
            return;
        }
        $key = $task->idempotency_key ?: (string) Str::uuid();
        $task->update(['idempotency_key' => $key]);
        RunPlatformAiTask::dispatchAfterResponse($task->id, $key);
    }

    public function recoverPendingExecutions(): int
    {
        $count = 0;
        PlatformAiTask::where('status', 'approved')->whereNull('executed_at')->whereNotNull('idempotency_key')
            ->each(function ($task) use (&$count) {
                $busy = PlatformAiTaskExecution::where('idempotency_key', $task->idempotency_key)
                    ->whereIn('status', ['running', 'completed'])->exists();
                if (! $busy) {
                    $this->dispatchExecution($task);
                    $count++;
                }
            });

        return $count;
    }

    public function execute(int $taskId, ?string $idempotencyKey = null): void
    {
        $task = PlatformAiTask::with('agent')->find($taskId);
        if (! $task || ! $task->agent || ! $this->canExecute($task)) {
            return;
        }
        $idempotencyKey ??= $task->idempotency_key;
        if (! $idempotencyKey || $task->idempotency_key !== $idempotencyKey) {
            return;
        }
        $lock = 'platform-ai-exec:'.$task->id;
        if (! Cache::add($lock, 1, now()->addMinutes(10))) {
            return;
        }
        if (PlatformAiTaskExecution::where('idempotency_key', $idempotencyKey)->where('status', 'completed')->exists()) {
            Cache::forget($lock);

            return;
        }
        $execution = PlatformAiTaskExecution::create([
            'idempotency_key' => $idempotencyKey, 'platform_ai_task_id' => $task->id,
            'task_version' => $task->approval_version, 'attempt' => $task->executions()->count() + 1,
            'status' => 'running', 'started_at' => now(),
        ]);

        $fail = function (string $reason) use ($task, $lock, $execution) {
            Cache::forget($lock);
            $task->update(['error' => $reason]);
            $execution->update(['status' => 'failed', 'error' => $reason, 'finished_at' => now()]);
            $task->agent->messages()->create([
                'role' => 'assistant',
                'content' => "Bos, tugas \"{$task->title}\" belum bisa kuselesaikan: {$reason} Tugasnya tetap di In Progress; geser lagi ke In Progress kalau mau kucoba ulang.",
                'meta' => ['mode' => 'internal', 'proactive' => true, 'task_id' => $task->id, 'failed' => true],
            ]);
        };

        if (! filled(config('services.yoka_ai.key'))) {
            $fail('koneksi AI belum aktif.');

            return;
        }
        $budgetKey = 'platform-ai-chat:'.now()->format('Y-m-d');
        $count = Cache::increment($budgetKey);
        Cache::put($budgetKey, $count, now()->endOfDay());
        if ($count > (int) config('services.yoka_ai.daily_request_budget', 250)) {
            $fail('jatah AI harian sudah penuh.');

            return;
        }

        try {
            $report = app(AgentBrain::class)->execute($task->agent, $task);
        } catch (\Throwable $e) {
            Log::warning('Platform AI task execution failed', ['task' => $task->id, 'exception' => class_basename($e)]);
            $fail('koneksi analisis sedang bermasalah.');

            return;
        }

        $task->update(['status' => 'completed', 'executed_at' => now(), 'result' => ['report' => $report], 'error' => null]);
        $execution->update(['status' => 'completed', 'result' => ['report' => $report], 'finished_at' => now()]);
        $task->agent->messages()->create([
            'role' => 'assistant', 'content' => $report,
            'meta' => ['mode' => 'ai', 'proactive' => true, 'task_id' => $task->id, 'executed' => true],
        ]);
        PlatformActivityLog::record('ai.task.executed', "{$task->agent->name} menyelesaikan tugas {$task->title}.", $task);
    }

    /**
     * Persetujuan lewat chat. Sengaja diputuskan di server, bukan oleh model: hanya balasan singkat
     * yang jelas menyetujui, dan hanya untuk tugas yang diusulkan agen di pesan terakhirnya.
     *
     * @return array<int,string> judul tugas yang disetujui
     */
    public function approveFromChat(PlatformAiAgent $agent, string $text, ?int $adminId): array
    {
        $text = trim($text);
        if (mb_strlen($text) > 60
            || ! preg_match('/^(ya|y|oke|ok|okay|siap|gas|lanjut|lanjutkan|setuju|saya setuju|aku setuju|approve|acc|silakan|boleh|go|deal)\b/iu', $text)
            || preg_match('/\b(tapi|jangan|tunggu|belum|ubah|revisi|ganti|batal|tidak|nggak|gak)\b/iu', $text)) {
            return [];
        }
        $last = $agent->messages()->where('role', 'assistant')->latest('id')->first();
        $ids = (array) data_get($last?->meta, 'proposed_tasks', []);
        if (count($ids) > 1) {
            $position = match (true) {
                preg_match('/\b(pertama|ke-?1|nomor 1)\b/iu', $text) === 1 => 0,
                preg_match('/\b(kedua|ke-?2|nomor 2)\b/iu', $text) === 1 => 1,
                preg_match('/\b(ketiga|ke-?3|nomor 3)\b/iu', $text) === 1 => 2,
                default => null,
            };
            if ($position === null || ! isset($ids[$position])) {
                return [];
            }
            $ids = [$ids[$position]];
        } elseif ($ids === []) {
            return [];
        }

        $titles = [];
        foreach (PlatformAiTask::with('agent')->whereIn('id', $ids)->where('status', 'proposed')->whereIn('type', ['chat_task', 'chat_decision', 'chat_strategy'])->get() as $task) {
            $task->update(['status' => 'approved', 'approved_by' => $adminId, 'approved_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
            PlatformActivityLog::record('ai.task.approve', "Menyetujui tugas {$task->title} lewat chat.", $task);
            $this->dispatchExecution($task->fresh());
            $titles[] = $task->title;
        }

        return $titles;
    }

    public function run(PlatformAiAgent $agent, bool $force = false): PlatformAiTask
    {
        if (! $force) {
            $existing = $agent->tasks()->where('type', 'analysis')->whereDate('created_at', today())->latest()->first();
            if ($existing) {
                return $existing;
            }
        }

        $insight = match ($agent->key) {
            'chief' => $this->chief(),
            'marketing' => $this->marketing(),
            'ads' => $this->ads(),
            'finance' => $this->finance(),
            'accounting' => $this->accounting(),
            'cs' => $this->cs(),
            'sales' => $this->sales(),
            default => ['Analisis belum tersedia', 'Periksa konfigurasi agen.', 'low', false, []],
        };

        [$summary, $recommendation, $risk, $approval, $payload] = $insight;
        $reasoning = $this->reason($agent, $summary, $recommendation, $payload);
        if ($reasoning) {
            $summary = $reasoning['summary'] ?? $summary;
            $recommendation = $reasoning['recommendation'] ?? $recommendation;
            $payload['ai_used'] = true;
            $payload['priorities'] = $reasoning['priorities'] ?? [];
            $payload['proactive_message'] = $reasoning['message'] ?? null;
        } else {
            $payload['ai_used'] = false;
        }
        $task = $agent->tasks()->create([
            'type' => 'analysis', 'title' => 'Analisis '.$agent->name.' · '.now()->format('d M Y'),
            'summary' => $summary, 'recommendation' => $recommendation,
            'risk' => $risk, 'requires_approval' => $approval,
            'status' => $approval ? 'proposed' : 'completed', 'payload' => $payload,
            'executed_at' => $approval ? null : now(),
        ]);
        $agent->messages()->create([
            'role' => 'assistant',
            'content' => $this->proactiveMessage($agent, $task),
            'meta' => [
                'mode' => ($payload['ai_used'] ?? false) ? 'ai' : 'internal',
                'proactive' => true,
                'task_id' => $task->id,
                'requires_approval' => $task->requires_approval,
            ],
        ]);
        $agent->update(['last_run_at' => now(), 'next_run_at' => now()->addDay()]);

        return $task;
    }

    /**
     * Dorongan proaktif tanpa biaya LLM: bila ada keputusan yang menunggu lebih dari sehari,
     * agen pemiliknya mengingatkan lewat chat (maksimal sekali per 12 jam per agen).
     */
    public function nudges(): int
    {
        $sent = 0;
        foreach (PlatformAiAgent::where('status', 'active')->get() as $agent) {
            $stale = $agent->tasks()->where('status', 'proposed')->where('created_at', '<=', now()->subDay())->orderBy('created_at')->limit(5)->get();
            if ($stale->isEmpty()) {
                continue;
            }
            $recent = $agent->messages()->where('role', 'assistant')->where('created_at', '>=', now()->subHours(12))
                ->get()->contains(fn ($m) => (bool) data_get($m->meta, 'nudge'));
            if ($recent) {
                continue;
            }
            $list = $stale->map(fn ($t) => '- '.$t->title)->join("\n");
            $agent->messages()->create([
                'role' => 'assistant',
                'content' => "Bos, ada {$stale->count()} hal dariku yang sudah lebih dari sehari nunggu keputusanmu:\n{$list}\n\nKalau kamu lagi sibuk, bilang saja mana yang mau kuurutkan duluan atau kutunda. Yang berisiko tinggi sebaiknya jangan terlalu lama menggantung.",
                'meta' => ['mode' => 'internal', 'proactive' => true, 'nudge' => true],
            ]);
            $sent++;
        }

        return $sent;
    }

    private function proactiveMessage(PlatformAiAgent $agent, PlatformAiTask $task): string
    {
        if (filled(data_get($task->payload, 'proactive_message'))) {
            return trim((string) data_get($task->payload, 'proactive_message'));
        }

        $opening = match ($agent->key) {
            'chief' => 'Bos, ada yang perlu kita putuskan hari ini.',
            'marketing' => 'Bos, aku habis lihat bahan konten kita.',
            'ads' => 'Bos, soal iklan: aku sudah cek kesiapan bahan dan batas belanjanya.',
            'finance' => 'Bos, aku sudah hitung posisi uang kita.',
            'accounting' => 'Bos, ada catatan yang perlu dirapikan.',
            'cs' => 'Bos, ada kabar dari sisi pelanggan.',
            'sales' => 'Bos, aku sudah lihat pipeline kita.',
            default => 'Bos, ada yang perlu kita bahas.',
        };
        $message = "{$opening}

{$task->summary}";
        if ($task->recommendation) {
            $message .= "

Menurutku: {$task->recommendation}";
        }
        $message .= $task->requires_approval
            ? '

Tugasnya sudah kusiapkan di papan. Tinggal kamu setujui, belum ada yang keluar sebelum itu.'
            : '

Kalau mau, kita bedah bareng sekarang.';

        return $message;
    }

    private function chief(): array
    {
        $pending = PlatformAiTask::whereIn('status', ['proposed', 'approved'])->count();
        $trials = BusinessSubscription::where('status', 'trial')->whereBetween('ends_at', [today(), today()->addDays(7)])->count();
        $leads = PlatformAiRecord::where('kind', 'lead')->whereNotIn('status', ['won', 'lost'])->count();

        return ["Ada {$pending} keputusan terbuka, {$trials} trial segera berakhir, dan {$leads} lead aktif.", 'Prioritaskan keputusan berisiko tinggi, follow-up trial, lalu isi pipeline konten dan sales.', 'low', false, compact('pending', 'trials', 'leads')];
    }

    private function marketing(): array
    {
        $content = PlatformAiRecord::where('kind', 'content')->orderByDesc('score')->get();
        $best = $content->first();
        $summary = $content->isEmpty() ? 'Belum ada data konten yang dicatat.' : "Menganalisis {$content->count()} konten. Kandidat terkuat: {$best->title} (skor {$best->score}/100).";
        $recommendation = $best ? 'Uji konten dengan skor tertinggi. Pastikan hook, retention, share, klik, dan lead menjadi dasar keputusan.' : 'Tambahkan URL konten dan metrik Instagram/TikTok agar agen dapat memberi skor yang dapat diaudit.';

        return [$summary, $recommendation, 'low', false, ['content_count' => $content->count(), 'best_content_id' => $best?->id]];
    }

    private function ads(): array
    {
        $candidate = PlatformAiRecord::where('kind', 'content')->where('score', '>=', 70)->orderByDesc('score')->first();
        $budget = (int) PlatformAiAgent::where('key', 'ads')->value('daily_budget');
        if (! $candidate) {
            return ['Belum ada konten dengan skor minimal 70.', 'Jangan belanjakan saldo sebelum Marketing menemukan kandidat tervalidasi.', 'medium', true, ['action' => 'external_ads', 'ready' => false]];
        }

        return ["Konten {$candidate->title} layak menjadi eksperimen iklan. Batas harian Rp ".number_format($budget, 0, ',', '.').'.', 'Buat eksperimen kecil 3 hari. Campaign hanya dapat dikirim setelah akun iklan terhubung dan pemilik menyetujui.', 'high', true, ['action' => 'external_ads', 'content_id' => $candidate->id, 'daily_budget' => $budget, 'ready' => $budget > 0]];
    }

    private function finance(): array
    {
        $revenue = (float) BusinessSubscription::where('status', 'active')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount');
        $spend = (float) PlatformAiRecord::where('kind', 'finance')->where('status', 'expense')->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $margin = $revenue - $spend;

        return ['Pendapatan langganan bulan ini Rp '.number_format($revenue, 0, ',', '.').', biaya tercatat Rp '.number_format($spend, 0, ',', '.').', margin sementara Rp '.number_format($margin, 0, ',', '.').'.', $revenue === 0 ? 'Tahan peningkatan anggaran iklan sampai pendapatan dan biaya akuisisi dapat diukur.' : 'Batasi eksperimen iklan berdasarkan payback dan catat semua biaya agar margin tidak bias.', 'low', false, compact('revenue', 'spend', 'margin')];
    }

    private function accounting(): array
    {
        $paid = BusinessSubscription::where('status', 'active')->whereNotNull('paid_at')->count();
        $missing = BusinessSubscription::where('status', 'active')->whereNull('paid_at')->count();

        return ["{$paid} langganan aktif memiliki waktu pembayaran; {$missing} perlu direkonsiliasi.", $missing ? 'Periksa langganan aktif tanpa paid_at sebelum laporan keuangan ditutup.' : 'Data langganan aktif lengkap untuk pemeriksaan awal.', $missing ? 'medium' : 'low', false, compact('paid', 'missing')];
    }

    private function cs(): array
    {
        [$trialSummary, , , , $trialPayload] = app(TrialAgent::class)->work(PlatformAiAgent::where('key', 'cs')->firstOrFail());

        $open = PlatformAiRecord::where('kind', 'ticket')->whereNotIn('status', ['resolved', 'closed'])->count();
        $urgent = PlatformAiRecord::where('kind', 'ticket')->where('score', '>=', 80)->whereNotIn('status', ['resolved', 'closed'])->count();

        return ["Ada {$open} tiket terbuka; {$urgent} bertanda mendesak. {$trialSummary}", $open ? 'Siapkan jawaban berdasarkan knowledge base. Refund, keamanan, dan data sensitif wajib dieskalasi.' : 'Inbox bersih. Pastikan WhatsApp Business resmi terhubung sebelum balasan otomatis diaktifkan.', 'medium', true, ['action' => 'external_message', 'open' => $open, 'urgent' => $urgent, 'trial' => $trialPayload]];
    }

    private function sales(): array
    {
        $leads = PlatformAiRecord::where('kind', 'lead')->whereNotIn('status', ['won', 'lost'])->orderByDesc('score')->get();
        $hot = $leads->where('score', '>=', 70)->count();

        return ["Pipeline berisi {$leads->count()} lead aktif; {$hot} memiliki skor minimal 70.", $hot ? 'Siapkan pendekatan personal untuk lead terkuat. Jangan mengirim pesan massal atau berulang.' : 'Tambahkan prospek dengan sumber dan alasan kecocokan; agen tidak akan melakukan scraping atau spam.', 'medium', true, ['action' => 'external_message', 'active' => $leads->count(), 'hot' => $hot]];
    }

    private function permissions(string $key): array
    {
        return match ($key) {
            'ads' => ['read_content', 'read_finance', 'propose_campaign'],
            'finance', 'accounting' => ['read_subscriptions', 'read_finance'],
            'cs' => ['read_tickets', 'draft_reply', 'read_subscriptions', 'draft_whatsapp'],
            'sales' => ['read_leads', 'draft_outreach'],
            default => ['read_platform_metrics', 'create_recommendation'],
        };
    }

    private function reason(PlatformAiAgent $agent, string $summary, string $recommendation, array $facts): ?array
    {
        if (! filled(config('services.yoka_ai.key'))) {
            return null;
        }

        $budgetKey = 'platform-ai:'.now()->format('Y-m-d');
        if (Cache::increment($budgetKey) > 50) {
            return null;
        }
        Cache::put($budgetKey, Cache::get($budgetKey), now()->endOfDay());
        $voice = $this->voice($agent);

        try {
            $response = Http::baseUrl(rtrim((string) config('services.yoka_ai.url'), '/'))
                ->withToken(config('services.yoka_ai.key'))
                ->timeout((int) config('services.yoka_ai.timeout', 25))
                ->post('/chat/completions', [
                    'model' => config('services.yoka_ai.deep_model') ?: config('services.yoka_ai.model'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => "Kamu adalah {$agent->name}, {$agent->department} di tim internal Yoka POS dan teman kerja pemilik Yoka. Karaktermu: {$voice}. Analisis hanya fakta yang diberikan; jangan mengarang angka atau mengklaim tindakan eksternal sudah dilakukan. Balas JSON valid dengan key summary, recommendation, priorities (maksimal 3 string), dan message. Summary dan recommendation ringkas untuk kartu pekerjaan. Field message adalah chat WhatsApp 40-110 kata dari seorang rekan kerja yang ahli: mulai langsung dari temuan paling penting (bukan sapaan formal), pakai 'aku' dan 'kamu', panggil 'Bos', kalimat pendek dan luwes, boleh 'nah', 'oke', 'jujur ya'. Beri pendapatmu sendiri, bukan hanya ringkasan angka: apa artinya, apa yang kamu sarankan, apa risikonya bila dibiarkan. Tutup dengan satu langkah konkret atau satu pertanyaan, bukan kalimat template. Bila tindakan butuh persetujuan, bilang natural bahwa kamu menunggu lampu hijau. Tanpa heading, tanpa 'sebagai AI', tanpa emoji, tanpa 'saya telah memeriksa'."],
                        ['role' => 'user', 'content' => json_encode(['baseline_summary' => $summary, 'baseline_recommendation' => $recommendation, 'facts' => $facts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ])->throw();

            $content = data_get($response->json(), 'choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            return is_array($decoded) ? array_intersect_key($decoded, array_flip(['summary', 'recommendation', 'priorities', 'message'])) : null;
        } catch (\Throwable $e) {
            Log::warning('Platform AI reasoning failed', ['agent' => $agent->key, 'exception' => class_basename($e)]);

            return null;
        }
    }

    private function voice(PlatformAiAgent $agent): string
    {
        return self::PROFILES[$agent->key]['voice'] ?? 'rekan kerja yang hangat, lugas, dan fokus pada langkah nyata';
    }
}
