<?php

namespace App\Services\PlatformFinance;

use App\Models\BusinessSubscription;
use App\Models\PlatformAiRun;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Line;
use Illuminate\Support\Carbon;

/**
 * Pemeriksaan pembukuan untuk Nanda (pengendalian internal, Financial Accounting bab 8; rekonsiliasi bab 8.6).
 * Hanya MEMBACA dan melaporkan temuan. Tidak ada yang diubah otomatis.
 */
class Review
{
    public function __construct(private Ledger $ledger) {}

    public function run(?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $findings = [];

        $tb = $this->ledger->trialBalance($asOf);
        if (! $tb['balanced']) {
            $findings[] = ['severity' => 'high', 'issue' => 'Neraca saldo tidak seimbang', 'detail' => "Debit {$tb['total_debit']} vs kredit {$tb['total_credit']}."];
        }

        // Pembayaran Midtrans yang aktif tapi belum terjurnal (webhook gagal mencatat).
        $paidSubs = BusinessSubscription::where('status', 'active')->whereNotNull('paid_at')->whereNotNull('payment_token')->where('amount', '>', 0)->pluck('id');
        $posted = Entry::where('idempotency_key', 'like', 'midtrans:subscription:%')->pluck('idempotency_key')
            ->map(fn ($k) => (int) substr($k, strlen('midtrans:subscription:')));
        $missing = $paidSubs->diff($posted)->values();
        if ($missing->isNotEmpty()) {
            $findings[] = ['severity' => 'high', 'issue' => 'Pembayaran langganan belum masuk buku besar', 'detail' => 'Langganan #'.$missing->take(10)->implode(', #').'. Jalankan php artisan yoka:finance-sync-midtrans.'];
        }

        // Saldo Midtrans menumpuk = pencairan belum dicatat / belum direkonsiliasi.
        $midtrans = $this->ledger->balances(null, $asOf)['1200']['balance'] ?? 0;
        $lastPayout = Entry::where('description', 'like', 'Pencairan Midtrans%')->max('entry_date');
        if ($midtrans > 0 && (! $lastPayout || Carbon::parse($lastPayout)->lt($asOf->copy()->subDays(7)))) {
            $findings[] = ['severity' => 'medium', 'issue' => 'Saldo Midtrans belum direkonsiliasi', 'detail' => 'Saldo tertahan Rp '.number_format($midtrans, 0, ',', '.').' dan tidak ada pencairan dicatat 7 hari terakhir. Cocokkan dengan laporan settlement Midtrans.'];
        }
        if ($midtrans < 0) {
            $findings[] = ['severity' => 'high', 'issue' => 'Saldo Midtrans negatif', 'detail' => 'Pencairan dicatat melebihi pembayaran yang masuk. Kemungkinan salah input atau pembayaran belum tersinkron.'];
        }

        // Transaksi manual tanpa bukti.
        $noEvidence = Entry::where('source', 'manual')->whereNull('evidence')->whereNull('reversal_of_id')->whereNull('reversed_by_id')
            ->where('entry_date', '>=', $asOf->copy()->subDays(60))->count();
        if ($noEvidence > 0) {
            $findings[] = ['severity' => 'low', 'issue' => 'Transaksi tanpa bukti', 'detail' => "{$noEvidence} jurnal manual 60 hari terakhir belum punya bukti (invoice/struk)."];
        }

        // Kemungkinan dobel: tanggal, akun, dan nominal sama.
        $dups = Line::query()->join('platform_fin_entries as e', 'e.id', '=', 'platform_fin_lines.platform_fin_entry_id')
            ->whereNull('e.reversal_of_id')->whereNull('e.reversed_by_id')->where('e.source', 'manual')->where('platform_fin_lines.debit', '>', 0)
            ->groupBy('e.entry_date', 'platform_fin_lines.platform_fin_account_id', 'platform_fin_lines.debit')
            ->havingRaw('COUNT(*) > 1')->selectRaw('e.entry_date, platform_fin_lines.platform_fin_account_id, platform_fin_lines.debit, COUNT(*) as n')->get();
        foreach ($dups as $d) {
            $findings[] = ['severity' => 'medium', 'issue' => 'Kemungkinan transaksi dobel', 'detail' => "{$d->n} jurnal dengan tanggal ".Carbon::parse($d->entry_date)->toDateString().', akun sama, nominal '.number_format((float) $d->debit, 0, ',', '.').'.'];
        }

        // Estimasi biaya AI (telemetri) vs beban AI yang benar-benar dicatat dari invoice.
        $from = $asOf->copy()->startOfMonth();
        $estimateUsd = (float) PlatformAiRun::whereBetween('created_at', [$from, $asOf->copy()->endOfDay()])->sum('estimated_cost_usd');
        $recorded = $this->ledger->balances($from, $asOf)['5100']['balance'] ?? 0;
        if ($estimateUsd > 0 && $recorded <= 0) {
            $findings[] = ['severity' => 'low', 'issue' => 'Biaya AI belum dicatat dari invoice', 'detail' => 'Telemetri memperkirakan USD '.round($estimateUsd, 2).' bulan ini. Ini ESTIMASI, bukan beban; catat beban AI saat invoice/tagihan kartu keluar.'];
        }

        // Mutasi rekening yang sudah diimpor tapi belum dicocokkan ke jurnal.
        $unmatched = app(Reconciliation::class)->unmatchedCount(null, $asOf);
        if ($unmatched > 0) {
            $findings[] = ['severity' => 'medium', 'issue' => 'Mutasi rekening belum dicocokkan', 'detail' => "{$unmatched} baris mutasi bank/Midtrans belum ada pasangannya di buku besar. Catat transaksinya atau tandai diabaikan dengan alasan."];
        }

        // Saldo kredit OpenAI (biaya persis dari Costs API).
        $ai = app(OpenAiBilling::class)->status();
        if ($ai['low']) {
            $findings[] = ['severity' => 'high', 'issue' => 'Saldo kredit OpenAI rendah', 'detail' => 'Sisa US$'.number_format($ai['balance_usd'], 2).($ai['days_left'] !== null ? ', sekitar '.$ai['days_left'].' hari lagi' : '').'. Top-up sebelum layanan AI berhenti.'];
        }

        // Tagihan (hutang usaha) yang lewat jatuh tempo.
        $ap = app(ExpenseWorkflow::class)->payables($asOf);
        if ($ap['overdue'] > 0) {
            $findings[] = ['severity' => 'medium', 'issue' => 'Tagihan lewat jatuh tempo', 'detail' => 'Rp '.number_format($ap['overdue'], 0, ',', '.').' hutang usaha belum dibayar melewati jatuh tempo.'];
        }

        // Talangan pemilik yang belum diganti.
        $reimb = $this->ledger->balances(null, $asOf)['2200']['balance'] ?? 0;
        if ($reimb > 0) {
            $findings[] = ['severity' => 'low', 'issue' => 'Talangan pemilik belum diganti', 'detail' => 'Yoka berhutang Rp '.number_format($reimb, 0, ',', '.').' ke pemilik.'];
        }

        return [
            'as_of' => $asOf->toDateString(),
            'trial_balance_balanced' => $tb['balanced'],
            'findings' => $findings,
            'summary' => $findings === [] ? 'Tidak ada temuan.' : count($findings).' temuan.',
        ];
    }
}
