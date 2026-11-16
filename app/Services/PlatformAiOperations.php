<?php

namespace App\Services;

use App\Models\BusinessSubscription;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiMessage;
use App\Models\PlatformAiRecord;
use App\Models\PlatformAiTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlatformAiOperations
{
    public const DEFINITIONS = [
        'chief' => ['Chief of Staff', 'Direction', 'Menyatukan pekerjaan seluruh agen menjadi prioritas bisnis harian.', 'auto_internal'],
        'marketing' => ['Marketing Intelligence', 'Marketing', 'Menganalisis konten dan menemukan topik serta video yang layak diuji.', 'recommend'],
        'ads' => ['Ads Manager', 'Growth', 'Menyusun eksperimen iklan dengan batas anggaran dan menghentikan pemborosan.', 'approval'],
        'finance' => ['Finance Strategist', 'Finance', 'Mengukur margin, CAC, LTV, runway, dan memberi simulasi keputusan.', 'recommend'],
        'accounting' => ['Accounting', 'Finance', 'Merekonsiliasi pendapatan, biaya, dan kelengkapan pencatatan.', 'auto_internal'],
        'cs' => ['Customer Service', 'Customer', 'Menjawab, menindaklanjuti, dan mengeskalasi percakapan pelanggan.', 'approval'],
        'sales' => ['Sales Development', 'Sales', 'Menilai prospek, menyiapkan pendekatan personal, dan menjaga pipeline.', 'approval'],
    ];

    public const PROFILES = [
        'chief' => ['title' => 'Koordinator bisnis', 'initials' => 'CS', 'accent' => 'emerald', 'voice' => 'partner strategi yang tenang, tegas, melihat gambaran besar, dan berani menyebut satu prioritas utama', 'prompts' => ['Apa prioritas bisnis hari ini?', 'Keputusan apa yang tertunda?', 'Ringkas kondisi semua tim']],
        'marketing' => ['title' => 'Strategi konten', 'initials' => 'MI', 'accent' => 'cyan', 'voice' => 'rekan kreatif yang antusias tetapi tetap berbasis data, suka membahas hook, audiens, dan ide yang konkret', 'prompts' => ['Konten mana paling potensial?', 'Ide konten berikutnya apa?', 'Apa pola dari konten terbaik?']],
        'ads' => ['title' => 'Eksperimen iklan', 'initials' => 'AM', 'accent' => 'sky', 'voice' => 'media buyer yang lugas, hati-hati memakai uang, dan selalu bicara hipotesis, batas rugi, serta ukuran keberhasilan', 'prompts' => ['Konten mana layak diiklankan?', 'Berapa anggaran uji aman?', 'Apa syarat campaign siap?']],
        'finance' => ['title' => 'Strategi keuangan', 'initials' => 'FS', 'accent' => 'amber', 'voice' => 'partner keuangan yang membumi, terus terang soal risiko, dan menjelaskan angka dengan bahasa sederhana', 'prompts' => ['Bagaimana margin bulan ini?', 'Berapa batas belanja aman?', 'Apa risiko keuangan terbesar?']],
        'accounting' => ['title' => 'Kontrol pembukuan', 'initials' => 'AC', 'accent' => 'teal', 'voice' => 'rekan pembukuan yang teliti, rapi, tidak menghakimi, dan langsung menunjukkan data mana yang perlu dibereskan', 'prompts' => ['Apa data yang belum lengkap?', 'Perlu rekonsiliasi apa?', 'Ringkas pencatatan bulan ini']],
        'cs' => ['title' => 'Layanan pelanggan', 'initials' => 'CS', 'accent' => 'rose', 'voice' => 'rekan customer service yang hangat, sabar, peka pada nada pelanggan, tetapi tegas saat perlu eskalasi', 'prompts' => ['Ada tiket yang mendesak?', 'Buat draf balasan pelanggan', 'Apa yang perlu dieskalasi?']],
        'sales' => ['title' => 'Pengembangan penjualan', 'initials' => 'SD', 'accent' => 'violet', 'voice' => 'rekan sales yang aktif dan percaya diri tanpa memaksa, fokus pada kebutuhan prospek dan langkah follow-up berikutnya', 'prompts' => ['Lead mana harus dihubungi?', 'Buat pendekatan untuk lead terbaik', 'Bagaimana kondisi pipeline?']],
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

    public function reply(PlatformAiAgent $agent, string $question): array
    {
        $snapshot = $this->snapshot($agent);
        $voice = $this->voice($agent);
        $history = $agent->messages()->latest()->limit(12)->get()->reverse()->map(fn (PlatformAiMessage $message) => [
            'role' => $message->role === 'assistant' ? 'assistant' : 'user',
            'content' => $message->content,
        ])->values()->all();

        if (! filled(config('services.yoka_ai.key'))) {
            return [
                'content' => $this->offlineReply($agent, $question, $snapshot),
                'mode' => 'internal',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }

        if ($agent->status !== 'active') {
            return [
                'content' => "Bro, status kerjaku sedang dijeda. Chat kita tetap aman, tapi aku belum menjalankan analisis baru sampai kamu mengaktifkanku lagi.\n\n".$this->offlineReply($agent, $question, $snapshot),
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
                'content' => "Jatah analisis AI hari ini sudah penuh, bro. Biar biaya tetap terkontrol, untuk sementara aku pakai data internal yang sudah ada.\n\n".$this->offlineReply($agent, $question, $snapshot),
                'mode' => 'internal',
                'snapshot' => $snapshot,
                'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? [],
            ];
        }

        try {
            $messages = array_merge([[
                'role' => 'system',
                'content' => "Kamu adalah {$agent->name}, rekan kerja sungguhan di tim internal Yoka POS, bukan chatbot layanan dan bukan sistem pelapor. Karaktermu: {$voice}. Misi: {$agent->mission} Bicara alami seperti kolega yang sudah mengenal pemilik bisnis. Ikuti gaya bahasa pengguna; kalau ia santai dan memakai kata bro, kamu boleh membalas santai tanpa berlebihan. Langsung tanggapi maksudnya, jangan membuka dengan 'berdasarkan data', 'sebagai AI', 'saya menerima instruksi', atau bahasa laporan. Pakai kalimat pendek dan 2-4 paragraf bila perlu. Boleh bertanya balik satu hal yang relevan. Gunakan hanya snapshot dan riwayat yang tersedia. Jika data kurang, jelaskan secara natural apa yang belum ada. Jangan mengklaim sudah menjalankan tindakan eksternal. Maksimal 220 kata, tanpa emoji dan tanpa tabel Markdown.",
            ]], $history, [[
                'role' => 'user',
                'content' => json_encode(['pertanyaan' => $question, 'snapshot_sistem' => $snapshot], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]]);
            $response = Http::baseUrl(rtrim((string) config('services.yoka_ai.url'), '/'))
                ->withToken(config('services.yoka_ai.key'))
                ->timeout((int) config('services.yoka_ai.timeout', 25))
                ->post('/chat/completions', [
                    'model' => config('services.yoka_ai.deep_model') ?: config('services.yoka_ai.model'),
                    'temperature' => 0.25,
                    'messages' => $messages,
                ])->throw();
            $content = trim((string) data_get($response->json(), 'choices.0.message.content'));
            if ($content === '') {
                throw new \RuntimeException('Respons model kosong.');
            }

            return ['content' => $content, 'mode' => 'ai', 'snapshot' => $snapshot, 'suggestions' => self::PROFILES[$agent->key]['prompts'] ?? []];
        } catch (\Throwable $e) {
            Log::warning('Platform AI chat failed', ['agent' => $agent->key, 'error' => $e->getMessage()]);

            return [
                'content' => "Koneksi analisisku lagi bermasalah, bro. Aku tetap coba bantu dari data internal yang sudah masuk.\n\n".$this->offlineReply($agent, $question, $snapshot),
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

    private function offlineReply(PlatformAiAgent $agent, string $question, array $snapshot): string
    {
        $records = collect($snapshot['records'])->map(fn ($total, $kind) => "{$total} {$kind}")->join(', ');
        $latest = $snapshot['latest_analysis'];
        $opening = match ($agent->key) {
            'chief' => 'Aku tangkap maksudmu. Untuk sekarang aku lihat dulu kondisi tim dari angka yang benar-benar ada.',
            'marketing' => 'Oke, aku tangkap arah yang kamu mau. Bahan yang bisa kubaca sekarang masih terbatas.',
            'ads' => 'Oke bro, aku cek dari sisi kesiapan iklan dulu. Aku nggak mau asal menyuruh belanja.',
            'finance' => 'Aku paham pertanyaannya. Biar aman, aku jawab dari angka yang sudah tercatat dulu.',
            'accounting' => 'Oke, aku cek dari catatan yang sudah masuk. Aku nggak akan menebak data yang belum ada.',
            'cs' => 'Aku ngerti. Aku lihat dulu percakapan dan tiket yang memang sudah tercatat.',
            'sales' => 'Sip, aku lihat dulu pipeline yang benar-benar ada supaya follow-up kita nggak asal.',
            default => 'Aku paham maksudmu. Aku jawab dari data yang sudah ada dulu, ya.',
        };
        $answer = $opening;
        $answer .= "\n\nSaat ini aku bisa melihat ".($records ?: 'belum ada catatan operasional').", {$snapshot['open_tasks']} tugas terbuka, dan {$snapshot['completed_tasks']} tugas selesai.";
        if ($latest) {
            $answer .= "\n\nTemuan terakhirku: {$latest['summary']}";
            if ($latest['recommendation']) {
                $answer .= " Menurutku, {$latest['recommendation']}";
            }
        } else {
            $answer .= "\n\nAku belum punya analisis sebelumnya. Tambahkan data yang relevan atau jalankan analisis, nanti kita bedah bareng di sini.";
        }

        return $answer;
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

    private function proactiveMessage(PlatformAiAgent $agent, PlatformAiTask $task): string
    {
        if (filled(data_get($task->payload, 'proactive_message'))) {
            return trim((string) data_get($task->payload, 'proactive_message'));
        }

        $opening = match ($agent->key) {
            'chief' => 'Bro, ada beberapa hal yang menurutku perlu kita rapikan hari ini.',
            'marketing' => 'Bro, aku barusan lihat bahan konten yang kita punya.',
            'ads' => 'Bro, aku sudah cek kesiapan bahan iklan dan batas belanjanya.',
            'finance' => 'Bro, aku sudah lihat posisi uang dan catatan biaya kita.',
            'accounting' => 'Bro, aku baru selesai mencocokkan catatan yang masuk.',
            'cs' => 'Bro, aku sudah cek kondisi percakapan pelanggan kita.',
            'sales' => 'Bro, aku sudah lihat pipeline dan calon pelanggan yang ada.',
            default => 'Bro, aku menemukan sesuatu yang perlu kita bahas.',
        };
        $message = "{$opening}\n\n{$task->summary}";
        if ($task->recommendation) {
            $message .= "\n\nMenurutku langkah paling masuk akal sekarang: {$task->recommendation}";
        }
        $message .= $task->requires_approval
            ? "\n\nAku sudah siapkan tugasnya. Kalau kamu setuju, tinggal approve—aku belum akan melakukan tindakan keluar sebelum itu."
            : "\n\nKalau kamu mau, kita bisa bedah angkanya bareng di sini.";

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
        $open = PlatformAiRecord::where('kind', 'ticket')->whereNotIn('status', ['resolved', 'closed'])->count();
        $urgent = PlatformAiRecord::where('kind', 'ticket')->where('score', '>=', 80)->whereNotIn('status', ['resolved', 'closed'])->count();

        return ["Ada {$open} tiket terbuka; {$urgent} bertanda mendesak.", $open ? 'Siapkan jawaban berdasarkan knowledge base. Refund, keamanan, dan data sensitif wajib dieskalasi.' : 'Inbox bersih. Pastikan WhatsApp Business resmi terhubung sebelum balasan otomatis diaktifkan.', 'medium', true, ['action' => 'external_message', 'open' => $open, 'urgent' => $urgent]];
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
            'cs' => ['read_tickets', 'draft_reply'],
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
                        ['role' => 'system', 'content' => "Kamu adalah {$agent->name}, rekan kerja internal Yoka POS. Karaktermu: {$voice}. Analisis hanya fakta agregat yang diberikan. Jangan mengarang angka atau mengklaim tindakan eksternal sudah dilakukan. Balas JSON valid dengan key summary, recommendation, priorities (maksimal 3 string), dan message. Summary dan recommendation tetap ringkas untuk kartu pekerjaan. Field message harus berupa pesan proaktif 60-140 kata dalam Bahasa Indonesia yang terdengar seperti chat manusia ke pemilik bisnis: mulai langsung dari temuan, santai bila cocok, boleh memakai 'bro' secara natural maksimal satu kali, tanpa heading, tanpa kata 'sebagai AI', tanpa kalimat 'saya baru selesai memeriksa', tanpa emoji. Tutup dengan satu langkah konkret atau pertanyaan yang mengundang balasan. Bila tindakan butuh approval, katakan secara natural bahwa kamu menunggu persetujuan."],
                        ['role' => 'user', 'content' => json_encode(['baseline_summary' => $summary, 'baseline_recommendation' => $recommendation, 'facts' => $facts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ])->throw();

            $content = data_get($response->json(), 'choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            return is_array($decoded) ? array_intersect_key($decoded, array_flip(['summary', 'recommendation', 'priorities', 'message'])) : null;
        } catch (\Throwable $e) {
            Log::warning('Platform AI reasoning failed', ['agent' => $agent->key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function voice(PlatformAiAgent $agent): string
    {
        return self::PROFILES[$agent->key]['voice'] ?? 'rekan kerja yang hangat, lugas, dan fokus pada langkah nyata';
    }
}
