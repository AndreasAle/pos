<?php

namespace App\Console\Commands;

use App\Models\BalanceTransaction;
use App\Models\Business;
use App\Models\CashierShift;
use App\Models\CustomerPoint;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\WithdrawalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipes the sales of one shop — used after a trial run with dummy orders.
 *
 * Only orders (with their items and add-ons), the QRIS balance entries they
 * created and closed shifts are removed. Products, categories, users and
 * settings are never touched. Without --force it only counts.
 *
 *   php artisan pos:reset-transactions relasi-coffee            (count only)
 *   php artisan pos:reset-transactions relasi-coffee --force
 *   php artisan pos:reset-transactions relasi-coffee --until=2026-11-04 --force
 */
class ResetBusinessTransactions extends Command
{
    protected $signature = 'pos:reset-transactions
                            {slug : Business slug, e.g. relasi-coffee}
                            {--until= : Only orders up to and including this date (YYYY-MM-DD)}
                            {--force : Actually delete; without it nothing changes}';

    protected $description = 'Delete the dummy sales of one business (orders, QRIS balance entries, closed shifts)';

    public function handle(): int
    {
        $business = Business::where('slug', $this->argument('slug'))->first();

        if (! $business) {
            $this->error('Business dengan slug itu tidak ditemukan.');

            return self::FAILURE;
        }

        $until = $this->option('until');
        if ($until !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $this->error('--until harus berformat YYYY-MM-DD.');

            return self::FAILURE;
        }

        $orders = Order::where('business_id', $business->id)
            ->when($until, fn ($q) => $q->where('created_at', '<', date('Y-m-d', strtotime($until . ' +1 day'))));
        $orderIds = (clone $orders)->pluck('id');

        $ledger = BalanceTransaction::where('business_id', $business->id)
            ->where('sourceable_type', Order::class)
            ->whereIn('sourceable_id', $orderIds);

        // Money that already left through a withdrawal cannot be undone here.
        if (WithdrawalRequest::where('business_id', $business->id)->exists()) {
            $this->error('Bisnis ini punya permintaan penarikan saldo — reset dibatalkan, saldo harus dibereskan manual.');

            return self::FAILURE;
        }

        // Points and stock moved by these orders are not reversed by this
        // command, so refuse rather than leave customers or stock wrong.
        if (CustomerPoint::whereIn('order_id', $orderIds)->exists()
            || StockMovement::whereIn('order_id', $orderIds)->exists()) {
            $this->error('Ada poin pelanggan atau pergerakan stok yang terkait order ini — reset dibatalkan agar angkanya tidak salah.');

            return self::FAILURE;
        }

        $shifts = CashierShift::where('business_id', $business->id)->where('status', 'closed')
            ->when($until, fn ($q) => $q->where('opened_at', '<', date('Y-m-d', strtotime($until . ' +1 day'))));

        // Net effect of the ledger rows: credits add, debits (refunds) subtract.
        $balanceDelta = (float) (clone $ledger)->get()
            ->sum(fn ($t) => $t->type === 'credit' ? $t->net_amount : -$t->net_amount);

        $this->line("Bisnis      : {$business->name}");
        $this->line('Order       : ' . $orderIds->count() . ' (omzet Rp ' . number_format((float) (clone $orders)->sum('grand_total'), 0, ',', '.') . ')');
        $this->line('Mutasi saldo: ' . (clone $ledger)->count() . ' (saldo berkurang Rp ' . number_format($balanceDelta, 0, ',', '.') . ')');
        $this->line('Shift tutup : ' . (clone $shifts)->count());

        if (! $this->option('force')) {
            $this->warn('Hanya hitung, belum ada yang dihapus. Tambahkan --force untuk menghapus.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($business, $orders, $ledger, $shifts, $balanceDelta) {
            $lockedBusiness = Business::whereKey($business->id)->lockForUpdate()->first();
            $lockedBusiness->balance      = (float) $lockedBusiness->balance - $balanceDelta;
            $lockedBusiness->total_earned = (float) $lockedBusiness->total_earned - $balanceDelta;
            $lockedBusiness->save();

            $ledger->delete();
            $orders->delete(); // order items and add-ons go with them
            $shifts->delete();
        });

        $this->info('Selesai. Produk, kategori, user, dan pengaturan tidak diubah.');

        return self::SUCCESS;
    }
}
