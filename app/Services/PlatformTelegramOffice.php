<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramMessage;
use App\Services\PlatformAgents\ResponseGuard;
use App\Services\PlatformAgents\WorkingMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Kehidupan kantor di grup Telegram: agen saling menimpali dan sesekali ngobrol sendiri
 * (progres kerja, ide pengembangan sistem, bercanda ringan) seperti grup kerja manusia.
 *
 * Batas keras ada di sini, bukan di prompt:
 *  - hanya grup yang aktif, di luar jam tenang, dan setelah grup sepi beberapa saat;
 *  - jatah obrolan spontan per grup per hari dan jumlah giliran per obrolan dibatasi;
 *  - obrolan spontan tidak boleh membuat tugas, mengirim pesan keluar, atau memakai uang
 *    (AgentBrain tetap hanya bisa membaca data dan mengusulkan tugas yang menunggu approval).
 */
class PlatformTelegramOffice
{
    public const DAILY_CHATTER_LIMIT = 10;

    public const MAX_CHATTER_TURNS = 4;

    public const IDLE_MINUTES = 20;

    /** Peluang satu rekan ikut menimpali setelah agen menjawab Bos. */
    public const CHIME_IN_CHANCE = 65;

    private const TOPICS = [
        'progress' => 'Ceritakan singkat ke grup apa yang lagi kamu pegang atau pikirkan hari ini di bidangmu (pakai data nyata lewat tool kalau perlu). Boleh colek satu rekan yang terkait.',
        'system' => 'Lempar satu ide atau keluhan kecil soal pengembangan sistem Yoka POS dari sudut bidangmu (fitur, alur trial, dashboard, bot, laporan). Sebut masalah nyatanya, bukan teori. Ajak rekan menanggapi.',
        'numbers' => 'Komentari satu angka atau kondisi bisnis Yoka hari ini yang menurutmu menarik atau bikin khawatir. Ambil datanya lewat tool dulu, jangan mengarang.',
        'casual' => 'Buka obrolan santai ala kantor: jam istirahat, kopi, capek, nyeletuk soal kerjaan. Ringan dan singkat, tetap sopan, tidak perlu data.',
    ];

    /** Di atas panjang ini jawaban santai ditulis ulang jadi chat pendek. */
    public const CASUAL_MAX_CHARS = 260;

    public function __construct(private PlatformTelegramGateway $gateway) {}

    /**
     * Langkah terakhir sebelum dikirim: kalau jawaban terlalu panjang/rapi untuk obrolan grup,
     * tulis ulang jadi gaya chat WhatsApp kantor. Permintaan yang memang butuh isi panjang
     * (ide, daftar, draf, strategi) dibiarkan. Gagal = pakai teks asli.
     */
    public function humanize(PlatformAiAgent $agent, string $text, string $request = ''): string
    {
        $text = PlatformTelegramStyle::clean($text);
        if (mb_strlen($text) <= self::CASUAL_MAX_CHARS || ! filled(config('services.yoka_ai.key'))
            || preg_match('/\b(ide|daftar|list|draf|draft|strategi|rencana|jadwal|langkah|laporan|rekap|detail|jelasin|jelaskan|caption|script|naskah)\b/iu', $request)) {
            return $text;
        }
        $telemetry = app(PlatformAiTelemetry::class);
        if ($telemetry->dailyBudgetReached()) {
            return $text;
        }
        $payload = [
            'model' => config('services.yoka_ai.model'),
            'temperature' => 0.9,
            'max_tokens' => 220,
            'messages' => [
                ['role' => 'system', 'content' => <<<'SYS'
Kamu menulis ulang pesan seorang karyawan untuk grup WhatsApp/Telegram kantor startup Indonesia.
Aturan:
- Pertahankan isi penting dan angka, buang basa-basi dan penjelasan berulang.
- Maksimal 2 bubble, pisahkan bubble dengan satu baris kosong. Tiap bubble 1-2 kalimat pendek (maks ~25 kata).
- Bahasa lisan santai: huruf kecil boleh, singkatan wajar (yg, gak, udh, blm, bgt, sih, deh, kok, wkwk bila cocok). Panggil "bos" untuk pemilik.
- Tanpa label nama di depan, tanpa Markdown, tanpa daftar bernomor, tanpa emoji berlebihan.
- Jangan pakai kalimat CS: "siap bantu", "ada yang bisa dibantu", "kalau mau aku bisa", "bilang aja".
- Jangan menjawab atas nama orang lain.
Contoh:
Asli: "Dari sisi keuangan, aku melihat bahwa trial selama 7 hari merupakan durasi yang seimbang antara kesempatan mencoba dan risiko arus kas, sehingga aku setuju dengan usulan tersebut."
Ulang: "setuju 7 hari sih, pas antara cobain sama aman buat cashflow"
Asli: "Saat ini belum ada pendaftar baru maupun pembayaran hari ini. Estimasi pemasukan bulanan juga masih nol."
Ulang: "sepi bos hari ini, blm ada daftar maupun bayar

mrr masih nol juga"
Balas hanya dengan pesan hasil tulis ulang.
SYS],
                ['role' => 'user', 'content' => $text],
            ],
        ];
        $started = microtime(true);
        try {
            $json = Http::baseUrl(rtrim((string) config('services.yoka_ai.url'), '/'))
                ->withToken(config('services.yoka_ai.key'))
                ->timeout((int) config('services.yoka_ai.timeout', 25))
                ->post('/chat/completions', $payload)->throw()->json();
            $telemetry->record($agent, 'telegram_humanize', (string) $payload['model'], $started, $json);
            $out = PlatformTelegramStyle::clean((string) data_get($json, 'choices.0.message.content', ''));

            return $out !== '' ? $out : $text;
        } catch (\Throwable $e) {
            $telemetry->record($agent, 'telegram_humanize', (string) $payload['model'], $started, [], $e);

            return $text;
        }
    }

    /**
     * Riwayat grup dari sudut pandang satu agen: pesannya sendiri = assistant (tanpa label),
     * ucapan Bos dan rekan = user dengan nama di depan. Label tidak pernah ditaruh di sisi
     * assistant supaya model tidak meniru format "[Nama]:" di balasannya.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function history(PlatformAiTelegramGroup $group, PlatformAiAgent $self, ?int $threadId, ?string $excludeInboundInteraction = null, int $limit = 16): array
    {
        return PlatformAiTelegramMessage::with('agent')
            ->where('platform_ai_telegram_group_id', $group->id)
            ->where('thread_id', $threadId)
            ->when($excludeInboundInteraction, fn ($q) => $q->where(fn ($q) => $q->where('interaction_id', '!=', $excludeInboundInteraction)->orWhereNull('interaction_id')->orWhere('direction', 'outbound')))
            ->latest('id')->limit($limit)->get()->reverse()
            ->map(function (PlatformAiTelegramMessage $message) use ($self) {
                if ($message->direction === 'outbound' && $message->platform_ai_agent_id === $self->id) {
                    return ['role' => 'assistant', 'content' => PlatformTelegramStyle::clean($message->content)];
                }
                $speaker = $message->direction === 'outbound' ? ($message->agent?->name ?? 'Rekan') : 'Bos';

                $content = $message->direction === 'outbound' ? PlatformTelegramStyle::clean($message->content) : $message->content;

                return ['role' => 'user', 'content' => "{$speaker}: {$content}"];
            })->values()->all();
    }

    /** Setelah agen menjawab Bos, kadang satu rekan lain ikut nimbrung kalau memang relevan. */
    public function maybeChimeIn(PlatformAiTelegramGroup $group, string $topic, array $spokenKeys, ?string $interactionId, ?int $threadId, ?int $roll = null): bool
    {
        $roll ??= random_int(1, 100);
        if ($roll > (int) config('services.telegram_office.chime_in_chance', self::CHIME_IN_CHANCE) || count($spokenKeys) >= 3) {
            return false;
        }
        $candidates = array_values(array_diff(app(PlatformTelegramRouter::class)->relevant($topic, 3), $spokenKeys));
        if ($candidates === []) {
            $candidates = array_values(array_diff(['chief'], $spokenKeys));
        }
        $agent = $this->activeAgentWithBot($candidates[0] ?? null);
        if (! $agent) {
            return false;
        }

        return $this->speak($group, $agent, 'Kamu baca obrolan di atas. Kalau ada yang bisa kamu tambah dari bidangmu, nimbrung singkat ke rekan atau Bos (1-2 kalimat, sebut nama yang kamu tanggapi). Kalau tidak ada yang berguna untuk ditambah, balas persis: SKIP', $interactionId, $threadId);
    }

    /** Dipanggil scheduler. Mengembalikan jumlah grup yang dimulai obrolannya. */
    public function chatter(?int $roll = null): int
    {
        if (! filled(config('services.yoka_ai.key'))) {
            return 0;
        }
        $started = 0;
        foreach (PlatformAiTelegramGroup::where('enabled', true)->get() as $group) {
            if ($this->runChatter($group, $roll)) {
                $started++;
            }
        }

        return $started;
    }

    public function runChatter(PlatformAiTelegramGroup $group, ?int $roll = null, bool $forced = false, ?string $topicHint = null): bool
    {
        $settings = (array) $group->notification_settings;
        if (! $forced && (data_get($settings, 'office_chatter', true) === false || $this->quiet($settings))) {
            return false;
        }
        $lastAt = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->max('created_at');
        if (! $forced && $lastAt && now()->diffInMinutes($lastAt, true) < self::IDLE_MINUTES) {
            return false;
        }
        $tz = data_get($settings, 'timezone', 'Asia/Jakarta');
        $counterKey = "telegram-office-chatter:{$group->id}:".now($tz)->format('Y-m-d');
        if (! $forced && (int) Cache::get($counterKey, 0) >= self::DAILY_CHATTER_LIMIT) {
            return false;
        }
        // Dicek tiap 15 menit; peluang kecil supaya kemunculannya tidak bisa ditebak jamnya.
        $roll ??= random_int(1, 100);
        if (! $forced && $roll > 35) {
            return false;
        }
        Cache::put($counterKey, (int) Cache::get($counterKey, 0) + 1, now($tz)->endOfDay());

        $topicKey = array_rand(self::TOPICS);
        $opening = self::TOPICS[$topicKey];
        if ($topicHint) {
            $opening = 'Bos minta kalian ngobrol di grup. Bahasannya: "'.Str::limit($topicHint, 300).'". Buka obrolannya dari sudut bidangmu dan colek rekan.';
        }
        $keys = collect(['chief', 'marketing', 'ads', 'finance', 'accounting', 'cs', 'sales'])->shuffle()->all();
        $starter = null;
        foreach ($keys as $key) {
            if ($starter = $this->activeAgentWithBot($key)) {
                break;
            }
        }
        if (! $starter) {
            return false;
        }
        // Kolom interaction_id bertipe UUID (36 karakter): jangan diberi awalan.
        $interactionId = (string) Str::uuid();
        if (! $this->speak($group, $starter, $opening.' Kamu yang membuka obrolan, jadi jangan menunggu ditanya.', $interactionId, null, false)) {
            return false;
        }

        // Rekan menimpali bergiliran, masing-masing membaca obrolan terbaru.
        $spoken = [$starter->key];
        $turns = $forced ? self::MAX_CHATTER_TURNS - 1 : random_int(2, self::MAX_CHATTER_TURNS - 1);
        foreach (collect($keys)->reject(fn ($k) => in_array($k, $spoken, true))->take($turns) as $key) {
            $agent = $this->activeAgentWithBot($key);
            if ($agent && $this->speak($group, $agent, 'Tanggapi obrolan rekan di atas seperti di grup kerja: setuju, bantah, tambah dari bidangmu, atau bercanda sedikit. Singkat. Kalau memang tidak ada yang mau kamu bilang, balas persis: SKIP', $interactionId, null)) {
                $spoken[] = $key;
            }
        }

        return true;
    }

    /**
     * Satu giliran bicara yang TIDAK diminta langsung oleh Bos (nimbrung / obrolan spontan).
     * Lewat dua gerbang: validasi klaim (ResponseGuard::validate) dan gerbang kontribusi
     * (ResponseGuard::lowValue). Pesan yang ditahan tidak pernah dikirim, jadi tidak bisa
     * memicu balasan bot lain.
     */
    private function speak(PlatformAiTelegramGroup $group, PlatformAiAgent $agent, string $instruction, ?string $interactionId, ?int $threadId, bool $gate = true): bool
    {
        $bot = PlatformAiTelegramBot::where('platform_ai_agent_id', $agent->id)->where('status', 'active')->first();
        if (! $bot) {
            return false;
        }
        $history = $this->history($group, $agent, $threadId);
        $question = "[Konteks sistem, bukan dari Bos: {$instruction} Jangan membuat tugas baru di obrolan ini.]";
        $result = app(PlatformAiOperations::class)->reply($agent, $question, [], [WorkingMemory::asFact($agent, $group, $threadId)], null, 'telegram_chatter', $history, (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : ''), ['allow_tasks' => false]);
        $text = trim((string) ($result['content'] ?? ''));
        if ($text === '' || preg_match('/^\W*skip\W*$/iu', $text) || ($result['mode'] ?? null) !== 'ai') {
            return false;
        }
        $guarded = ResponseGuard::validate($text, ['tools_used' => (array) ($result['tools_used'] ?? []), 'spoke_agents' => $this->recentSpeakers($group)]);
        $telemetry = app(PlatformAiTelemetry::class);
        foreach ($guarded['violations'] as $violation) {
            $telemetry->quality($violation === 'filler' ? 'filler_removed' : 'unsupported_claim_removed', $agent, ['type' => $violation]);
        }
        $text = $this->humanize($agent, $guarded['text']);
        if ($gate) {
            $recent = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->where('thread_id', $threadId)
                ->latest('id')->limit(6)->pluck('content')->all();
            if ($reason = ResponseGuard::lowValue($text, $recent)) {
                $telemetry->quality('low_value_suppressed', $agent, ['reason' => $reason]);

                return false;
            }
        } elseif ($text === '') {
            return false;
        }

        return (bool) $this->gateway->sendHuman($bot, $group, $text, $interactionId, $threadId);
    }

    /**
     * Agen A bertanya ke agen B atas permintaan Bos. B benar-benar dijalankan (bukan dikarang A),
     * jawabannya dikirim dengan identitas B dan disimpan sebagai kontribusi diskusi.
     * Dibatasi satu lompatan: B tidak boleh berkonsultasi lagi dan tidak boleh membuat tugas.
     *
     * @return array{name:string, content:string}|null
     */
    public function consult(PlatformAiTelegramGroup $group, PlatformAiAgent $asker, string $targetKey, string $request, ?string $interactionId, ?int $threadId, ?PlatformAdmin $admin): ?array
    {
        $target = $this->activeAgentWithBot($targetKey);
        if (! $target || $target->is($asker)) {
            return null;
        }
        $bot = PlatformAiTelegramBot::where('platform_ai_agent_id', $target->id)->where('status', 'active')->first();
        $question = "[Konteks sistem: Bos minta {$asker->name} menanyakan ini ke kamu: \"".Str::limit($request, 500).'". Jawab langsung ke '.$asker->name.' dengan analisis atau angka konkret dari bidangmu (ambil data lewat tool bila perlu). Jangan membuat tugas, jangan bertanya ke rekan lain.]';
        $result = app(PlatformAiOperations::class)->reply($target, $question, [], [WorkingMemory::asFact($target, $group, $threadId)], $admin, 'telegram_chat', $this->history($group, $target, $threadId), (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : ''), ['allow_tasks' => false]);
        if (($result['mode'] ?? null) !== 'ai') {
            return null;
        }
        $guarded = ResponseGuard::validate((string) $result['content'], ['tools_used' => (array) ($result['tools_used'] ?? []), 'spoke_agents' => $this->recentSpeakers($group)]);
        $content = PlatformTelegramStyle::clean($guarded['text']);
        if ($content === '') {
            return null;
        }
        $sent = $this->gateway->sendHuman($bot, $group, $content, $interactionId, $threadId);
        if ($admin) {
            $discussion = PlatformAiDiscussion::create(['created_by' => $admin->id, 'chair_agent_id' => $asker->id, 'objective' => Str::limit($request, 250), 'status' => 'completed', 'limits' => ['channel' => 'telegram', 'type' => 'consultation', 'max_agents' => 2, 'max_rounds' => 1, 'delegation_depth' => 1]]);
            $discussion->contributions()->create(['platform_ai_agent_id' => $target->id, 'round' => 1, 'content' => $content, 'context' => ['mode' => 'ai', 'asked_by' => $asker->key]]);
            if ($sent) {
                $sent->update(['platform_ai_discussion_id' => $discussion->id]);
            }
        }

        return ['name' => $target->name, 'content' => $content];
    }

    /** @return array<int,string> nama agen yang benar-benar punya pesan di grup ini 2 jam terakhir */
    private function recentSpeakers(PlatformAiTelegramGroup $group): array
    {
        $ids = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subHours(2))->whereNotNull('platform_ai_agent_id')->distinct()->pluck('platform_ai_agent_id');

        return PlatformAiAgent::whereIn('id', $ids)->pluck('name')->all();
    }

    private function activeAgentWithBot(?string $key): ?PlatformAiAgent
    {
        if (! $key) {
            return null;
        }
        $agent = PlatformAiAgent::where('key', $key)->where('status', 'active')->first();

        return $agent && PlatformAiTelegramBot::where('platform_ai_agent_id', $agent->id)->where('status', 'active')->exists() ? $agent : null;
    }

    private function quiet(array $settings): bool
    {
        $now = now(data_get($settings, 'timezone', 'Asia/Jakarta'))->format('H:i');
        $start = data_get($settings, 'quiet_start', '22:00');
        $end = data_get($settings, 'quiet_end', '07:00');

        return $start > $end ? ($now >= $start || $now < $end) : ($now >= $start && $now < $end);
    }
}
