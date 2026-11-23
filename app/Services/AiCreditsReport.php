<?php

namespace App\Services;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\AiCreditWallet;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * Laporan kredit Tanya Yoka untuk panel admin dan tim AI. Hanya baca, angka dari tabel nyata.
 * Dipakai: halaman Kredit AI di /platform, kartu Ringkasan Platform, Adit (CS), dan tool ai_credits_tenants.
 */
class AiCreditsReport
{
    /** Fakta resmi kredit AI dari config: satu sumber untuk panduan, Adit, dan agen. */
    public static function facts(): array
    {
        $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
        $free = (array) config('yoka_ai_credits.free_monthly', []);

        return [
            'aktif_di_server' => (bool) config('yoka_ai_credits.enabled'),
            'apa_itu' => 'Kredit Yoka AI adalah saldo untuk bertanya di Tanya Yoka (asisten AI di aplikasi). Terpisah dari langganan POS: kasir, stok, dan laporan tetap jalan walau kredit habis.',
            'pemakaian' => 'Pertanyaan biasa 1 kredit. Pertanyaan berat (lampiran file, audit bisnis lengkap) 2 sampai 10 kredit sesuai pemakaian. Sapaan singkat tidak dihitung.',
            'jatah_gratis_per_bulan' => [
                'masa_coba' => (int) ($free['free'] ?? 0),
                'YokaBasic' => (int) ($free['starter'] ?? 0),
                'Business' => (int) ($free['business'] ?? 0),
                'Custom' => (int) ($free['enterprise'] ?? 0),
                'aturan' => 'Terisi ulang tiap tanggal 1, sisa tidak dibawa ke bulan berikutnya. Toko tanpa paket aktif tidak dapat jatah gratis.',
            ],
            'paket_beli' => collect((array) config('yoka_ai_credits.packs', []))->map(fn ($p) => $p['credits'].' kredit '.$rp($p['price']).' ('.$rp($p['price'] / $p['credits']).' per kredit)')->values()->all(),
            'masa_berlaku_kredit_dibeli' => (int) config('yoka_ai_credits.paid_validity_months', 12).' bulan sejak pembelian terakhir',
            'cara_beli' => 'Di Tanya Yoka, buka menu akun di kiri bawah, pilih Penggunaan, lalu pilih paket dan bayar lewat Midtrans. Kredit masuk otomatis setelah pembayaran dikonfirmasi.',
            'saat_habis' => 'Kolom chat Tanya Yoka terkunci sampai kredit diisi lagi (beli kredit atau tunggu jatah gratis tanggal 1).',
            'batas_wewenang' => 'Kredit gratis tambahan, kompensasi, atau refund kredit hanya bisa diputuskan dan diberikan Bos (super admin). Agen AI tidak boleh menjanjikannya.',
        ];
    }

    public function platform(): array
    {
        $start = now()->startOfMonth();
        $paid = AiCreditTopup::where('status', 'paid')->where('paid_at', '>=', $start);
        $usage = AiCreditEntry::where('kind', 'usage')->where('created_at', '>=', $start);
        $wallets = AiCreditWallet::query();

        return [
            'topup_rupiah_bulan_ini' => (float) (clone $paid)->sum('amount'),
            'topup_transaksi_bulan_ini' => (clone $paid)->count(),
            'kredit_terpakai_bulan_ini' => -(int) (clone $usage)->sum(DB::raw('free_change + paid_change')),
            'kredit_terpakai_dari_berbayar' => -(int) (clone $usage)->sum('paid_change'),
            'toko_memakai_ai_bulan_ini' => (int) (clone $usage)->distinct()->count('business_id'),
            'saldo_kredit_berbayar_belum_terpakai' => (int) (clone $wallets)->sum('paid_balance'),
            'toko_kredit_habis' => (clone $wallets)->whereRaw('free_balance + paid_balance = 0')->count(),
            'topup_menunggu_bayar' => AiCreditTopup::where('status', 'pending')->where('created_at', '>=', now()->subDay())->count(),
        ];
    }

    /** Per toko, diurutkan dari yang paling aktif bulan ini. */
    public function businesses(int $limit = 50, ?string $filter = null): array
    {
        $start = now()->startOfMonth();
        $used = AiCreditEntry::where('kind', 'usage')->where('created_at', '>=', $start)
            ->selectRaw('business_id, -SUM(free_change + paid_change) used, MAX(created_at) last_used')->groupBy('business_id')->get()->keyBy('business_id');
        $topups = AiCreditTopup::where('status', 'paid')->selectRaw('business_id, MAX(paid_at) last_topup, SUM(amount) total')->groupBy('business_id')->get()->keyBy('business_id');

        $rows = AiCreditWallet::with('business:id,name')->get()->map(function ($w) use ($used, $topups) {
            $u = $used[$w->business_id] ?? null;
            $t = $topups[$w->business_id] ?? null;

            return [
                'business_id' => $w->business_id,
                'toko' => $w->business?->name,
                'saldo' => $w->total(),
                'gratis' => $w->free_balance,
                'berbayar' => $w->paid_balance,
                'terpakai_bulan_ini' => (int) ($u->used ?? 0),
                'terakhir_pakai' => $u?->last_used ? \Illuminate\Support\Carbon::parse($u->last_used)->toDateTimeString() : null,
                'total_beli_rupiah' => (float) ($t->total ?? 0),
                'beli_terakhir' => $t?->last_topup,
            ];
        });
        if ($filter === 'empty') {
            $rows = $rows->where('saldo', 0);
        } elseif ($filter === 'low') {
            $rows = $rows->where('saldo', '>', 0)->where('saldo', '<=', 5);
        }

        return $rows->sortByDesc('terpakai_bulan_ini')->take($limit)->values()->all();
    }

    /** Ringkasan untuk satu toko (untuk Adit saat toko itu chat). */
    public function forBusiness(Business $business): array
    {
        $b = app(AiCredits::class)->balance($business);
        $last = AiCreditTopup::where('business_id', $business->id)->latest('id')->first();

        return $b + [
            'terpakai_bulan_ini' => -(int) AiCreditEntry::where('business_id', $business->id)->where('kind', 'usage')
                ->where('created_at', '>=', now()->startOfMonth())->sum(DB::raw('free_change + paid_change')),
            'pembelian_terakhir' => $last ? ['status' => $last->status, 'kredit' => $last->credits, 'rupiah' => (float) $last->amount, 'tanggal' => $last->created_at->toDateString()] : null,
        ];
    }

    /** Satu kalimat konteks untuk prompt Adit. */
    public function promptLine(Business $business): string
    {
        if (! config('yoka_ai_credits.enabled')) {
            return '';
        }
        $s = $this->forBusiness($business);
        $line = "Kredit Yoka AI toko ini: sisa {$s['total']} kredit (gratis {$s['free']} dari jatah {$s['allowance']}, dibeli {$s['paid']}), terpakai {$s['terpakai_bulan_ini']} bulan ini.";
        if ($p = $s['pembelian_terakhir']) {
            $line .= " Pembelian terakhir {$p['kredit']} kredit tanggal {$p['tanggal']} status ".['paid' => 'lunas', 'pending' => 'menunggu bayar', 'cancelled' => 'batal'][$p['status']].'.';
        }

        return $line.' Kredit gratis tambahan, kompensasi, atau refund kredit hanya keputusan Bos: jangan dijanjikan, bilang akan diteruskan ke tim.';
    }
}
