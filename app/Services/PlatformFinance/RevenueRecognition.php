<?php

namespace App\Services\PlatformFinance;

use App\Models\BusinessSubscription;
use App\Models\PlatformFinance\RevenueSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengakuan pendapatan langganan (Financial Accounting bab 3: prinsip pengakuan pendapatan; bab 12: pendapatan diterima di muka).
 *
 * Kebijakan teknis: pembayaran untuk masa layanan <= 1 bulan diakui saat dibayar (perilaku lama, tidak berubah).
 * Masa layanan lebih dari 1 bulan (mis. paket tahunan) dicatat Dr Saldo Midtrans / Cr Pendapatan Diterima di Muka,
 * lalu diakui merata per bulan layanan (Dr 2400 / Cr 4000). Rumus pajak tidak disentuh di sini.
 */
class RevenueRecognition
{
    public function __construct(private Ledger $ledger) {}

    /** Jumlah bulan layanan: dibulatkan dari jumlah hari / 30, minimal 1. */
    public static function termMonths(BusinessSubscription $sub): int
    {
        // Durasi yang dibeli lebih akurat dari selisih tanggal (perpanjangan dihitung dari akhir masa lama).
        if ((int) $sub->months > 0) {
            return (int) $sub->months;
        }
        if (! $sub->starts_at || ! $sub->ends_at) {
            return 1;
        }

        return max(1, (int) round($sub->starts_at->diffInDays($sub->ends_at) / 30));
    }

    /** Buat jadwal per bulan (bagi rata dalam sen; sisa pembulatan masuk bulan terakhir). Idempoten per langganan. */
    public function schedule(BusinessSubscription $sub): void
    {
        $months = self::termMonths($sub);
        if ($months <= 1 || RevenueSchedule::where('business_subscription_id', $sub->id)->exists()) {
            return;
        }
        $total = Ledger::cents($sub->amount);
        $each = intdiv($total, $months);
        $start = Carbon::parse($sub->starts_at)->startOfMonth();
        DB::transaction(function () use ($sub, $months, $total, $each, $start) {
            for ($i = 0; $i < $months; $i++) {
                $cents = $i === $months - 1 ? $total - $each * ($months - 1) : $each;
                RevenueSchedule::create(['business_subscription_id' => $sub->id, 'period' => $start->copy()->addMonths($i)->format('Y-m'), 'amount' => $cents / 100.0]);
            }
        });
    }

    /** Akui pendapatan yang sudah jatuh waktu sampai bulan $asOf. Idempoten. */
    public function recognizeDue(?Carbon $asOf = null): int
    {
        $asOf ??= today();
        $count = 0;
        foreach (RevenueSchedule::whereNull('platform_fin_entry_id')->where('period', '<=', $asOf->format('Y-m'))->orderBy('period')->get() as $s) {
            $date = Carbon::parse($s->period.'-01');
            if ($this->ledger->isClosed($date)) {
                $date = today(); // periode sudah ditutup: diakui di periode berjalan
            }
            $entry = $this->ledger->post($date, "Pengakuan pendapatan langganan #{$s->business_subscription_id} bulan {$s->period}",
                [['2400', (float) $s->amount, 0], ['4000', 0, (float) $s->amount]], 'recognition', 'revenue-recognition:'.$s->id, 'SUB-'.$s->business_subscription_id);
            $s->update(['platform_fin_entry_id' => $entry->id]);
            $count++;
        }

        return $count;
    }

    public function deferredBalance(?Carbon $asOf = null): float
    {
        return (float) ($this->ledger->balances(null, $asOf ?? today())['2400']['balance'] ?? 0);
    }

    public function pendingCount(Carbon $until): int
    {
        return RevenueSchedule::whereNull('platform_fin_entry_id')->where('period', '<=', $until->format('Y-m'))->count();
    }
}
