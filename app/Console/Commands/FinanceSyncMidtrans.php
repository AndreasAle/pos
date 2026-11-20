<?php

namespace App\Console\Commands;

use App\Models\BusinessSubscription;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Console\Command;

/** Catat ke buku besar Yoka semua langganan Midtrans yang sudah dibayar tapi belum terjurnal. Aman diulang. */
class FinanceSyncMidtrans extends Command
{
    protected $signature = 'yoka:finance-sync-midtrans {--dry-run : Hanya tampilkan jumlahnya}';

    protected $description = 'Sinkronkan pembayaran langganan Midtrans ke buku besar keuangan Yoka (idempoten)';

    public function handle(Transactions $tx): int
    {
        $subs = BusinessSubscription::where('status', 'active')->whereNotNull('paid_at')->where('amount', '>', 0)
            ->whereNotNull('payment_token')->get();
        $posted = 0;
        foreach ($subs as $sub) {
            if ($this->option('dry-run')) {
                $posted++;

                continue;
            }
            $entry = $tx->subscriptionPaid($sub);
            $posted += $entry && $entry->wasRecentlyCreated ? 1 : 0;
        }
        $this->info(($this->option('dry-run') ? 'Akan diperiksa: ' : 'Jurnal baru: ').$posted.' dari '.$subs->count().' langganan berbayar.');

        return self::SUCCESS;
    }
}
