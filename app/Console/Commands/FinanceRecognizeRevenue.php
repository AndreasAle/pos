<?php

namespace App\Console\Commands;

use App\Services\PlatformFinance\FixedAssets;
use App\Services\PlatformFinance\RevenueRecognition;
use Illuminate\Console\Command;

/** Akui pendapatan diterima di muka yang sudah jatuh waktu, dan posting penyusutan bulan lalu. Idempoten. */
class FinanceRecognizeRevenue extends Command
{
    protected $signature = 'yoka:finance-recognize-revenue';

    protected $description = 'Keuangan Yoka: akui pendapatan paket > 1 bulan per bulan layanan dan posting penyusutan aset bulan lalu';

    public function handle(RevenueRecognition $recognition, FixedAssets $assets): int
    {
        $n = $recognition->recognizeDue();
        try {
            $dep = $assets->depreciate(today()->subMonth()->format('Y-m'));
        } catch (\InvalidArgumentException $e) {
            $dep = [];
            $this->warn('Penyusutan dilewati: '.$e->getMessage());
        }
        $this->info("{$n} jadwal pendapatan diakui, ".count($dep).' aset disusutkan.');

        return self::SUCCESS;
    }
}
