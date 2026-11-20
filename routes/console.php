<?php

use App\Jobs\RunIntelligenceAgent;
use App\Jobs\SendDailySummary;
use App\Jobs\SendLowStockAlert;
use App\Jobs\SendShiftUnclosedAlert;
use App\Models\Business;
use App\Models\IntelligenceAgent;
use App\Models\PlatformAiAgent;
use App\Services\InsightService;
use App\Services\Intelligence\AgentService;
use App\Services\PlatformAiEvents;
use App\Services\PlatformAiOperations;
use App\Services\PlatformHealth;
use App\Services\PlatformTelegramNotifications;
use App\Services\TelegramNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// ── Cek stok menipis — setiap 6 jam ──────────────────────────────────────────
Schedule::call(function () {
    Business::where('is_active', true)->each(function (Business $biz) {
        SendLowStockAlert::dispatch($biz);
    });
})->everySixHours()->name('low-stock-alert')->withoutOverlapping();

// ── Cek shift belum ditutup — setiap 2 jam ────────────────────────────────────
Schedule::call(function () {
    Business::where('is_active', true)->each(function (Business $biz) {
        SendShiftUnclosedAlert::dispatch($biz);
    });
})->everyTwoHours()->name('shift-unclosed-alert')->withoutOverlapping();

// ── Daily summary — setiap hari jam 21:00 ────────────────────────────────────
Schedule::call(function () {
    Business::where('is_active', true)->each(function (Business $biz) {
        SendDailySummary::dispatch($biz);
    });
})->dailyAt('21:00')->name('daily-summary')->withoutOverlapping();

// ── Telegram: dikirim langsung (tanpa antrean) supaya tidak butuh queue worker ──
Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::dailySummary($b)))
    ->dailyAt('21:00')->name('telegram-daily')->withoutOverlapping();

Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::weeklyDigest($b)))
    ->weeklyOn(1, '08:00')->name('telegram-weekly-ai')->withoutOverlapping();

Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::lowStock($b)))
    ->everySixHours()->name('telegram-low-stock')->withoutOverlapping();

Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::shiftUnclosed($b)))
    ->everyTwoHours()->name('telegram-shift-unclosed')->withoutOverlapping();

Schedule::call(fn () => Business::where('is_active', true)->each(fn ($b) => app(InsightService::class)->refresh($b)))
    ->hourly()->name('yoka-insights')->withoutOverlapping();

Schedule::call(function () {
    if (! config('pos.features.intelligence_agents')) {
        return;
    }
    Business::where('is_active', true)->each(fn (Business $business) => app(AgentService::class)->ensureDefaults($business));
    IntelligenceAgent::where('enabled', true)->each(function (IntelligenceAgent $agent) {
        $now = CarbonImmutable::now($agent->timezone);
        if (app(AgentService::class)->due($agent, $now)) {
            RunIntelligenceAgent::dispatchSync($agent->id, $now->toIso8601String());
        }
    });
})->everyMinute()->name('yoka-intelligence-agents')->withoutOverlapping();

// Detak cron: halaman Platform > Sistem membaca ini untuk memastikan schedule:run benar-benar berjalan tiap menit.
Schedule::call(fn () => Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->timestamp, 3600))
    ->everyMinute()->name('scheduler-heartbeat');

// Tim AI platform bekerja pada data internal. Tindakan eksternal tetap masuk antrean approval.
Schedule::call(function () {
    $operations = app(PlatformAiOperations::class);
    $operations->ensureAgents();
    PlatformAiAgent::where('status', 'active')
        ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
        ->each(fn ($agent) => $operations->run($agent));
})->hourly()->name('platform-ai-team')->withoutOverlapping();

// Pengingat proaktif: agen menyapa lewat chat bila ada keputusan yang menggantung lebih dari sehari.
Schedule::call(fn () => app(PlatformAiOperations::class)->nudges())->hourly()->name('platform-ai-nudges')->withoutOverlapping();

Schedule::call(fn () => app(PlatformAiEvents::class)->detect())->everyFifteenMinutes()->name('platform-ai-events')->withoutOverlapping();

Schedule::call(function () {
    app(PlatformAiOperations::class)->recoverPendingExecutions();
})->everyMinute()->name('platform-ai-task-dispatch')->withoutOverlapping();

// Shared hosting tidak selalu menyediakan process manager. Cron schedule:run Yoka
// memproses antrean database sampai kosong tanpa meninggalkan worker permanen.
Schedule::command('queue:work --stop-when-empty --tries=2 --timeout=180')
    ->everyMinute()->name('yoka-queue-drain')->withoutOverlapping(10);

Schedule::call(fn () => app(PlatformTelegramNotifications::class)->briefings())
    ->everyMinute()->name('platform-telegram-briefing')->withoutOverlapping();
Schedule::call(fn () => app(PlatformTelegramNotifications::class)->pendingAlerts())
    ->everyFiveMinutes()->name('platform-telegram-alerts')->withoutOverlapping();
// Obrolan kantor spontan antar agen di grup Telegram (jatah & jam tenang dibatasi di service).
Schedule::call(fn () => app(\App\Services\PlatformTelegramOffice::class)->chatter())
    ->everyFifteenMinutes()->name('platform-telegram-office-chatter')->withoutOverlapping(10);

// Keuangan Yoka: akui pendapatan diterima di muka yang jatuh waktu (idempoten).
Schedule::command('yoka:finance-recognize-revenue')->dailyAt('01:10')->name('finance-recognize-revenue')->withoutOverlapping();

// ── Backup database terenkripsi — harian 02:30, hanya bila YOKA_BACKUP_ENABLED=true ──
Schedule::command('yoka:backup')->dailyAt('02:30')->name('db-backup')->withoutOverlapping()
    ->when(fn () => (bool) config('backup.enabled'));
