<?php

namespace App\Console\Commands;

use App\Services\PlatformFinance\OpenAiBilling;
use App\Services\PlatformTelegramNotifications;
use Illuminate\Console\Command;
use Throwable;

/** Tarik biaya OpenAI persis dari Costs API, lalu kirim peringatan bila saldo kredit rendah. */
class FinanceSyncOpenAi extends Command
{
    protected $signature = 'yoka:finance-sync-openai {--days=31}';

    protected $description = 'Keuangan Yoka: sinkron biaya OpenAI dari Costs API (butuh OPENAI_ADMIN_KEY) dan cek saldo kredit';

    public function handle(OpenAiBilling $billing, PlatformTelegramNotifications $telegram): int
    {
        try {
            $rows = $billing->sync((int) $this->option('days'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $s = $billing->status();
        $this->info("{$rows} baris biaya disinkron. Bulan ini US$".number_format($s['this_month_usd'], 2)
            .($s['balance_usd'] !== null ? ', saldo US$'.number_format($s['balance_usd'], 2) : ', saldo awal belum dicatat').'.');
        if ($s['low']) {
            $telegram->lowAiCreditAlert($s['balance_usd'], $s['days_left']);
            $this->warn('Saldo rendah: peringatan dikirim.');
        }

        return self::SUCCESS;
    }
}
