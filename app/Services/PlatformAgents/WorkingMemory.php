<?php

namespace App\Services\PlatformAgents;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussionContribution;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramMessage;
use Illuminate\Support\Str;

/**
 * Keadaan kerja satu agen, dibangun ulang setiap kali dari catatan otoritatif
 * (tugas, kontribusi diskusi, pesan Telegram yang tersimpan). Tidak ada tabel baru dan
 * tidak ada sumber kebenaran kedua: kalau catatannya berubah, memori kerja ikut berubah.
 *
 * Cakupan: tugas milik agen itu sendiri, dan percakapan hanya dari grup yang sedang aktif
 * (bukan dari grup lain atau chat dashboard), supaya konteks antar-saluran tidak tercampur.
 */
class WorkingMemory
{
    private const WINDOW_HOURS = 24;

    private const SNIPPET = 220;

    /** @return array<string,mixed> */
    public static function for(PlatformAiAgent $agent, ?PlatformAiTelegramGroup $group = null, ?int $threadId = null): array
    {
        $since = now()->subHours(self::WINDOW_HOURS);
        $snip = fn (?string $text) => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $text)), self::SNIPPET, '…');

        $open = PlatformAiTask::where('platform_ai_agent_id', $agent->id)->whereIn('status', ['proposed', 'approved'])
            ->latest('id')->limit(5)->get(['id', 'title', 'status', 'executed_at', 'error'])
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'status' => $t->status === 'proposed' ? 'menunggu approve' : 'sudah di-approve, sedang/akan dikerjakan sistem', 'blocker' => $t->error])->all();

        $done = PlatformAiTask::where('platform_ai_agent_id', $agent->id)->where('status', 'completed')->where('executed_at', '>=', now()->subDays(3))
            ->latest('executed_at')->limit(3)->get(['id', 'title', 'result', 'executed_at'])
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'result' => $snip(data_get($t->result, 'report')), 'at' => $t->executed_at?->diffForHumans()])->all();

        $contributions = PlatformAiDiscussionContribution::with('discussion:id,objective')->where('platform_ai_agent_id', $agent->id)
            ->where('created_at', '>=', $since)->latest('id')->limit(3)->get()
            ->map(fn ($c) => ['topik' => $snip($c->discussion?->objective), 'pendapatku' => $snip($c->content)])->all();

        $said = [];
        $corrections = [];
        if ($group) {
            $said = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->where('thread_id', $threadId)
                ->where('platform_ai_agent_id', $agent->id)->where('direction', 'outbound')->where('delivery_status', 'delivered')
                ->where('created_at', '>=', $since)->latest('id')->limit(4)->get(['content', 'created_at'])->reverse()
                ->map(fn ($m) => ['isi' => $snip($m->content), 'kapan' => $m->created_at?->diffForHumans()])->values()->all();
            $corrections = PlatformAiTelegramMessage::where('platform_ai_telegram_group_id', $group->id)->where('direction', 'inbound')
                ->where('created_at', '>=', $since)->latest('id')->limit(20)->get(['content'])
                ->filter(fn ($m) => preg_match('/\b(salah|keliru|bukan gitu|bukan begitu|koreksi|jangan|harusnya)\b/iu', (string) $m->content))
                ->take(2)->map(fn ($m) => $snip($m->content))->values()->all();
        }

        return array_filter([
            'tugas_terbuka' => $open,
            'baru_selesai' => $done,
            'diskusi_yang_kuikuti' => $contributions,
            'yang_tadi_kubilang_di_grup' => $said,
            'koreksi_bos_terbaru' => $corrections,
        ]);
    }

    /** Ringkas jadi fakta sistem untuk prompt. Kosong bila tidak ada catatan. */
    public static function asFact(PlatformAiAgent $agent, ?PlatformAiTelegramGroup $group = null, ?int $threadId = null): ?string
    {
        $memory = self::for($agent, $group, $threadId);
        if ($memory === []) {
            return 'Memori kerja: belum ada tugas, diskusi, atau ucapanmu yang tersimpan 24 jam terakhir.';
        }

        return 'Memori kerja (catatan tersimpan, pakai ini sebelum bilang "belum ada update"): '.json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
