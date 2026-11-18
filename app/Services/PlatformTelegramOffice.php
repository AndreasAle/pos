<?php

namespace App\Services;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramMessage;
use Illuminate\Support\Facades\Cache;
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
    public const DAILY_CHATTER_LIMIT = 6;

    public const MAX_CHATTER_TURNS = 4;

    public const IDLE_MINUTES = 40;

    /** Peluang satu rekan ikut menimpali setelah agen menjawab Bos. */
    public const CHIME_IN_CHANCE = 35;

    private const TOPICS = [
        'progress' => 'Ceritakan singkat ke grup apa yang lagi kamu pegang atau pikirkan hari ini di bidangmu (pakai data nyata lewat tool kalau perlu). Boleh colek satu rekan yang terkait.',
        'system' => 'Lempar satu ide atau keluhan kecil soal pengembangan sistem Yoka POS dari sudut bidangmu (fitur, alur trial, dashboard, bot, laporan). Sebut masalah nyatanya, bukan teori. Ajak rekan menanggapi.',
        'numbers' => 'Komentari satu angka atau kondisi bisnis Yoka hari ini yang menurutmu menarik atau bikin khawatir. Ambil datanya lewat tool dulu, jangan mengarang.',
        'casual' => 'Buka obrolan santai ala kantor: jam istirahat, kopi, capek, nyeletuk soal kerjaan. Ringan dan singkat, tetap sopan, tidak perlu data.',
    ];

    public function __construct(private PlatformTelegramGateway $gateway) {}

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
                    return ['role' => 'assistant', 'content' => $message->content];
                }
                $speaker = $message->direction === 'outbound' ? ($message->agent?->name ?? 'Rekan') : 'Bos';

                return ['role' => 'user', 'content' => "{$speaker}: {$message->content}"];
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

    public function runChatter(PlatformAiTelegramGroup $group, ?int $roll = null): bool
    {
        $settings = (array) $group->notification_settings;
        if (data_get($settings, 'office_chatter', true) === false || $this->quiet($settings)) {
            return false;
        }
        $lastAt = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->max('created_at');
        if ($lastAt && now()->diffInMinutes($lastAt, true) < self::IDLE_MINUTES) {
            return false;
        }
        $tz = data_get($settings, 'timezone', 'Asia/Jakarta');
        $counterKey = "telegram-office-chatter:{$group->id}:".now($tz)->format('Y-m-d');
        if ((int) Cache::get($counterKey, 0) >= self::DAILY_CHATTER_LIMIT) {
            return false;
        }
        // Dicek tiap 15 menit; peluang kecil supaya kemunculannya tidak bisa ditebak jamnya.
        $roll ??= random_int(1, 100);
        if ($roll > 20) {
            return false;
        }
        Cache::put($counterKey, (int) Cache::get($counterKey, 0) + 1, now($tz)->endOfDay());

        $topicKey = array_rand(self::TOPICS);
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
        $interactionId = 'chatter-'.Str::uuid();
        if (! $this->speak($group, $starter, self::TOPICS[$topicKey].' Kamu yang membuka obrolan, jadi jangan menunggu ditanya.', $interactionId, null)) {
            return false;
        }

        // Rekan menimpali bergiliran, masing-masing membaca obrolan terbaru.
        $spoken = [$starter->key];
        $turns = random_int(1, self::MAX_CHATTER_TURNS - 1);
        foreach (collect($keys)->reject(fn ($k) => in_array($k, $spoken, true))->take($turns) as $key) {
            $agent = $this->activeAgentWithBot($key);
            if ($agent && $this->speak($group, $agent, 'Tanggapi obrolan rekan di atas seperti di grup kerja: setuju, bantah, tambah dari bidangmu, atau bercanda sedikit. Singkat. Kalau memang tidak ada yang mau kamu bilang, balas persis: SKIP', $interactionId, null)) {
                $spoken[] = $key;
            }
        }

        return true;
    }

    private function speak(PlatformAiTelegramGroup $group, PlatformAiAgent $agent, string $instruction, ?string $interactionId, ?int $threadId): bool
    {
        $bot = PlatformAiTelegramBot::where('platform_ai_agent_id', $agent->id)->where('status', 'active')->first();
        if (! $bot) {
            return false;
        }
        $history = $this->history($group, $agent, $threadId);
        $question = "[Konteks sistem, bukan dari Bos: {$instruction} Jangan membuat tugas baru di obrolan ini.]";
        $result = app(PlatformAiOperations::class)->reply($agent, $question, [], [], null, 'telegram_chatter', $history, (string) $group->telegram_chat_id.($threadId ? ':'.$threadId : ''));
        $text = trim((string) ($result['content'] ?? ''));
        if ($text === '' || preg_match('/^\W*skip\W*$/iu', $text) || ($result['mode'] ?? null) === 'internal') {
            return false;
        }

        return (bool) $this->gateway->sendHuman($bot, $group, $text, $interactionId, $threadId);
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
