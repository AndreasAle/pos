<?php

namespace App\Services;

use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlatformTelegramNotifications
{
    /** Link dashboard yang sama tidak diulang ke grup yang sama dalam jendela ini. */
    public const LINK_COOLDOWN_MINUTES = 30;

    public function __construct(private PlatformTelegramGateway $gateway) {}

    public function briefings(): int
    {
        $sent = 0;
        foreach (PlatformAiTelegramGroup::where('enabled', true)->get() as $group) {
            $settings = (array) $group->notification_settings;
            if (! data_get($settings, 'daily_briefing') || data_get($settings, 'briefing_time', '08:00') !== now('Asia/Jakarta')->format('H:i')) {
                continue;
            }
            if (! Cache::add("telegram-office:briefing:{$group->id}:".today('Asia/Jakarta')->toDateString(), 1, now('Asia/Jakarta')->endOfDay())) {
                continue;
            }
            $bot = $this->chief();
            if (! $bot) {
                continue;
            }
            $pending = PlatformAiTask::where('status', 'proposed')->count();
            $running = PlatformAiTask::where('status', 'approved')->count();
            $done = PlatformAiTask::where('status', 'completed')->whereDate('updated_at', today())->count();
            if ($pending + $running + $done === 0) {
                continue;
            }
            $message = "Briefing pagi:\n• {$pending} keputusan menunggu\n• {$running} pekerjaan berjalan\n• {$done} pekerjaan selesai hari ini";
            if ($this->gateway->send($bot, $group, $message)->delivery_status === 'delivered') {
                $sent++;
            }
        }

        return $sent;
    }

    public function pendingAlerts(): int
    {
        $sent = 0;
        $pending = PlatformAiTask::where('status', 'proposed')->where('created_at', '<=', now()->subDay())->count();
        if ($pending === 0) {
            return 0;
        }
        foreach (PlatformAiTelegramGroup::where('enabled', true)->get() as $group) {
            if (! data_get($group->notification_settings, 'alerts', true)) {
                continue;
            }
            if ($this->quiet($group)) {
                continue;
            }
            $max = (int) data_get($group->notification_settings, 'max_alerts_per_day', 5);
            $countKey = "telegram-office:alerts:{$group->id}:".today('Asia/Jakarta')->toDateString();
            Cache::add($countKey, 0, now('Asia/Jakarta')->endOfDay());
            if ((int) Cache::get($countKey) >= $max) {
                continue;
            }
            // Pengingat sah, tapi keadaan yang sama (jumlah tertunda sama, hari sama) hanya sekali.
            // Klaim lewat ledger: aman bila dua worker menjalankan jadwal ini bersamaan.
            $claim = PlatformAiTelegramNotification::claim('pending_alert', $group, null, $pending.':'.today('Asia/Jakarta')->toDateString());
            if (! $claim) {
                continue;
            }
            $bot = $this->chief();
            if (! $bot) {
                $claim->update(['status' => 'skipped']);

                continue;
            }
            $message = $this->gateway->send($bot, $group, "Ada {$pending} keputusan yang sudah menunggu lebih dari sehari. Cek Operations Center sebelum pekerjaan berikutnya tertahan.");
            $claim->update(['status' => $message->delivery_status, 'platform_ai_telegram_message_id' => $message->id]);
            if ($message->delivery_status === 'delivered') {
                Cache::increment($countKey);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Kalimat penutup untuk tugas yang BARU saja tersimpan dari obrolan grup.
     * - Tiap tugas (versi approval yang sama) hanya diumumkan sekali per grup/topik.
     * - Link dashboard hanya disertakan bila belum dikirim ke grup ini dalam jendela cooldown.
     * Mengembalikan null bila tidak ada yang baru untuk diumumkan.
     *
     * @param  array<int,int>  $taskIds
     */
    public function proposalNotice(PlatformAiTelegramGroup $group, ?int $threadId, array $taskIds): ?string
    {
        $tasks = PlatformAiTask::whereIn('id', $taskIds)->where('status', 'proposed')->get();
        $fresh = $tasks->filter(fn (PlatformAiTask $task) => PlatformAiTelegramNotification::claim(
            'task_proposed', $group, $threadId, $task->id.':v'.(int) $task->approval_version,
            ['platform_ai_task_id' => $task->id, 'task_version' => (int) $task->approval_version]
        ) !== null);
        if ($fresh->isEmpty()) {
            app(PlatformAiTelemetry::class)->quality('approval_notice_deduplicated', null, ['count' => count($taskIds)]);

            return null;
        }
        $titles = $fresh->pluck('title')->map(fn ($t) => '"'.Str::limit($t, 60).'"')->join(', ');
        $bucket = intdiv(now()->timestamp, self::LINK_COOLDOWN_MINUTES * 60);
        $withLink = PlatformAiTelegramNotification::claim('dashboard_link', $group, $threadId, (string) $bucket) !== null;

        return "masuk papan buat di-approve: {$titles}".($withLink ? "\n".url('/platform/ai-team').'?tab=tasks' : '');
    }

    /**
     * Hasil tugas yang berasal dari Telegram dikirim balik ke grup/topik asal, memakai bot agen
     * yang bertanggung jawab. Hanya untuk tugas yang benar-benar selesai atau benar-benar gagal,
     * dan hanya sekali per versi tugas (aman terhadap worker paralel dan retry).
     */
    public function taskOutcome(PlatformAiTask $task, bool $success, ?string $reason = null): bool
    {
        $origin = (array) data_get($task->payload, 'telegram_origin', []);
        $group = isset($origin['group_id']) ? PlatformAiTelegramGroup::where('id', $origin['group_id'])->where('enabled', true)->first() : null;
        if (! $group || ! $task->agent) {
            return false;
        }
        // Tidak bocor ke grup milik admin lain: grup asal harus tetap milik admin yang sama.
        if (isset($origin['admin_id']) && (int) $origin['admin_id'] !== (int) $group->platform_admin_id) {
            return false;
        }
        if ($success && ($task->status !== 'completed' || ! $task->executed_at)) {
            return false;
        }
        $threadId = isset($origin['thread_id']) ? (int) $origin['thread_id'] : null;
        $claim = PlatformAiTelegramNotification::claim(
            $success ? 'task_completed' : 'task_failed', $group, $threadId,
            $task->id.':v'.(int) $task->approval_version.':'.($success ? 'ok' : 'fail:'.$task->executions()->count()),
            ['platform_ai_task_id' => $task->id, 'task_version' => (int) $task->approval_version]
        );
        if (! $claim) {
            return false;
        }
        $bot = PlatformAiTelegramBot::where('platform_ai_agent_id', $task->platform_ai_agent_id)->where('status', 'active')->first();
        if (! $bot) {
            $claim->update(['status' => 'skipped']);

            return false;
        }
        $link = url('/platform/ai-team').'?tab=tasks';
        if ($success) {
            $report = PlatformTelegramStyle::clean((string) data_get($task->result, 'report', ''));
            $text = "bos, \"{$task->title}\" udah selesai.\n\n".Str::limit($report, 1500, '…')."\n\nlengkapnya: {$link}";
        } else {
            $text = "bos, \"{$task->title}\" gagal kukerjakan: ".($reason ?: 'ada kendala teknis.')."\n\nbelum ada hasil apa pun dari tugas ini. detail: {$link}";
        }
        $message = $this->gateway->sendHuman($bot, $group, $text, isset($origin['interaction_id']) ? (string) $origin['interaction_id'] : null, $threadId, ['task_id' => $task->id]);
        $claim->update(['status' => $message?->delivery_status ?? 'failed', 'platform_ai_telegram_message_id' => $message?->id]);
        app(PlatformAiTelemetry::class)->quality($success ? 'task_result_delivered' : 'task_failure_reported', $task->agent, ['delivered' => $message?->delivery_status === 'delivered']);

        return $message?->delivery_status === 'delivered';
    }

    private function quiet(PlatformAiTelegramGroup $group): bool
    {
        $settings = (array) $group->notification_settings;
        $now = now(data_get($settings, 'timezone', 'Asia/Jakarta'))->format('H:i');
        $start = data_get($settings, 'quiet_start', '22:00');
        $end = data_get($settings, 'quiet_end', '07:00');

        return $start > $end ? ($now >= $start || $now < $end) : ($now >= $start && $now < $end);
    }

    private function chief(): ?PlatformAiTelegramBot
    {
        return PlatformAiTelegramBot::whereHas('agent', fn ($q) => $q->where('key', 'chief'))->where('status', 'active')->first();
    }
}
