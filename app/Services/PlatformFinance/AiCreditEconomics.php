<?php

namespace App\Services\PlatformFinance;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\AiCreditWallet;
use Illuminate\Support\Carbon;

/**
 * Ekonomi Kredit Yoka AI (Tanya Yoka) untuk Luna. Hanya baca, angka deterministik.
 * - Top-up yang dibayar = kas masuk, tetapi kredit yang BELUM terpakai adalah kewajiban (pendapatan diterima di muka),
 *   bukan pendapatan. Pendapatan diakui saat kredit dipakai atau hangus.
 * - Kredit gratis bulanan adalah biaya promosi/layanan: tanpa pendapatan, tetap memakan biaya OpenAI.
 * - Biaya per kredit: dari biaya OpenAI aktual bulan ini dibagi kredit terpakai; bila belum ada data, pakai batas atas teoretis dari config (ditandai asumsi).
 */
final class AiCreditEconomics
{
    public function __construct(private OpenAiBilling $openai) {}

    public function summary(?string $period = null): array
    {
        $start = $period ? Carbon::createFromFormat('Y-m', $period)->startOfMonth() : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $packs = (array) config('yoka_ai_credits.packs', []);

        $paid = AiCreditTopup::where('status', 'paid')->whereBetween('paid_at', [$start, $end]);
        $topupRevenue = (float) (clone $paid)->sum('amount');
        $creditsSold = (int) (clone $paid)->sum('credits');
        $byPack = (clone $paid)->selectRaw('pack, COUNT(*) n, SUM(amount) amount, SUM(credits) credits')->groupBy('pack')->get()
            ->map(fn ($r) => ['pack' => $packs[$r->pack]['name'] ?? $r->pack, 'transaksi' => (int) $r->n, 'rupiah' => (float) $r->amount, 'kredit' => (int) $r->credits])->values()->all();

        $entries = AiCreditEntry::whereBetween('created_at', [$start, $end]);
        $usedFree = -(int) (clone $entries)->where('kind', 'usage')->sum('free_change');
        $usedPaid = -(int) (clone $entries)->where('kind', 'usage')->sum('paid_change');
        $expiredPaid = -(int) (clone $entries)->where('kind', 'expire_paid')->sum('paid_change');
        $granted = (int) (clone $entries)->where('kind', 'grant_monthly')->sum('free_change');
        $activeUsers = (int) (clone $entries)->where('kind', 'usage')->distinct('business_id')->count('business_id');

        // Harga rata-rata per kredit terjual (sepanjang waktu) untuk menilai kewajiban dan pengakuan pendapatan.
        $allPaid = AiCreditTopup::where('status', 'paid');
        $avgPrice = ($c = (int) (clone $allPaid)->sum('credits')) > 0 ? (float) (clone $allPaid)->sum('amount') / $c : null;
        $outstanding = (int) AiCreditWallet::sum('paid_balance');

        $rate = $this->openai->rate() ?? (float) config('yoka_ai_credits.economics.idr_per_usd_assumption', 16500);
        $costUsd = $start->isSameMonth(now()) ? (float) ($this->openai->status()['this_month_usd'] ?? 0) : null;
        $usedTotal = $usedFree + $usedPaid;
        $theoretical = $this->theoreticalCostPerCredit($rate);
        $actualPerCredit = ($costUsd !== null && $usedTotal > 0 && $costUsd > 0) ? round($costUsd * $rate / $usedTotal, 1) : null;
        $perCredit = $actualPerCredit ?? $theoretical;
        $feePct = (float) config('yoka_ai_credits.economics.payment_fee_pct', 0);

        $packMargins = [];
        foreach ($packs as $key => $p) {
            $price = $p['price'] / max(1, $p['credits']);
            $net = $price * (1 - $feePct / 100);
            $packMargins[] = ['pack' => $p['name'], 'harga_per_kredit' => round($price, 1), 'biaya_ai_per_kredit' => round($perCredit, 1),
                'margin_per_kredit' => round($net - $perCredit, 1), 'margin_pct' => $net > 0 ? round(($net - $perCredit) / $net * 100, 1) : null];
        }

        return [
            'periode' => $start->format('Y-m'),
            'kas_masuk_topup' => $topupRevenue, 'kredit_terjual' => $creditsSold, 'per_paket' => $byPack,
            'kredit_terpakai' => ['berbayar' => $usedPaid, 'gratis' => $usedFree, 'total' => $usedTotal, 'toko_aktif' => $activeUsers],
            'kredit_gratis_diberikan' => $granted,
            'pendapatan_diakui_estimasi' => $avgPrice !== null ? round(($usedPaid + $expiredPaid) * $avgPrice, 0) : 0,
            'kewajiban_kredit_belum_terpakai' => ['kredit' => $outstanding, 'rupiah_estimasi' => $avgPrice !== null ? round($outstanding * $avgPrice, 0) : 0],
            'biaya_ai' => [
                'openai_bulan_ini_usd' => $costUsd, 'kurs' => $rate,
                'per_kredit_rp' => round($perCredit, 1), 'sumber' => $actualPerCredit !== null ? 'aktual (biaya OpenAI / kredit terpakai)' : 'ASUMSI batas atas teoretis dari config (belum ada data aktual)',
                'biaya_kredit_gratis_rp' => round($usedFree * $perCredit, 0),
                'catatan' => 'Biaya OpenAI mencakup SEMUA pemakaian AI Yoka (juga agen internal & Telegram), jadi biaya per kredit aktual cenderung terlalu tinggi.',
            ],
            'margin_per_paket' => $packMargins,
            'fee_pembayaran_pct' => $feePct ?: 'belum diisi (margin belum dikurangi fee Midtrans)',
        ];
    }

    /** Biaya maksimum 1 kredit bila seluruh 12.000 token tertimbang dipakai: token tertimbang x harga input per token. */
    public function theoreticalCostPerCredit(float $rate): float
    {
        $tokens = (int) config('yoka_ai_credits.tokens_per_credit', 12000);
        $inputUsdPerMillion = (float) config('yoka_ai_credits.economics.input_usd_per_million', 0.40);

        return $tokens / 1_000_000 * $inputUsdPerMillion * $rate;
    }
}
