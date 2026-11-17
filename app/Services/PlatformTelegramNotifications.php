<?php

namespace App\Services;

use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use Illuminate\Support\Facades\Cache;

class PlatformTelegramNotifications
{
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
            if ((int) Cache::get($countKey) >= $max || ! Cache::add("telegram-office:pending:{$group->id}:{$pending}", 1, now()->addHours(12))) {
                continue;
            }
            $bot = $this->chief();
            if (! $bot) {
                continue;
            }
            if ($this->gateway->send($bot, $group, "Ada {$pending} keputusan yang sudah menunggu lebih dari sehari. Cek Operations Center sebelum pekerjaan berikutnya tertahan.")->delivery_status === 'delivered') {
                Cache::increment($countKey);
                $sent++;
            }
        }

        return $sent;
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
