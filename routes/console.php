<?php

use App\Jobs\SendDailySummary;
use App\Jobs\SendLowStockAlert;
use App\Jobs\SendShiftUnclosedAlert;
use App\Models\Business;
use App\Services\TelegramNotifier;
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

Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::lowStock($b)))
    ->everySixHours()->name('telegram-low-stock')->withoutOverlapping();

Schedule::call(fn () => TelegramNotifier::linkedBusinesses()->each(fn ($b) => TelegramNotifier::shiftUnclosed($b)))
    ->everyTwoHours()->name('telegram-shift-unclosed')->withoutOverlapping();
