<?php

namespace App\Services\PlatformFinance;

use App\Models\AiCreditTopup;
use App\Models\BusinessSubscription;
use App\Models\PlatformFinance\Entry;
use InvalidArgumentException;

/**
 * Klasifikasi transaksi Yoka menjadi jurnal yang benar. Inilah aturan akuntansi yang deterministik
 * (bukan tebakan AI). Prinsip penting:
 *  - tidak semua uang masuk adalah pendapatan (modal pemilik, pinjaman, pindah dana bukan pendapatan),
 *  - Midtrans = akun penampung (clearing): pembayaran masuk ke Saldo Midtrans, fee jadi beban, pencairan
 *    ke bank adalah pindah dana, BUKAN pendapatan kedua kali,
 *  - biaya yang dibayar pemilik dengan uang pribadi tetap beban Yoka; lawannya modal, pinjaman, atau talangan.
 */
class Transactions
{
    /** Cara pemilik membiayai pengeluaran Yoka dari uang pribadinya. */
    public const OWNER_FUNDING = [
        'capital' => '3000',       // disetor sebagai modal, tidak perlu dikembalikan
        'loan' => '2100',          // pinjaman pemilik, akan dikembalikan
        'reimbursement' => '2200', // talangan, akan diganti dari kas Yoka
    ];

    /** Biaya ditagih tapi belum dibayar: dikreditkan ke Hutang Usaha. */
    public const PAYABLE = 'payable';

    /** Biaya yang memakai kredit yang sudah dibayar di muka (mis. kredit OpenAI): dikreditkan ke Biaya Dibayar di Muka. */
    public const PREPAID = 'prepaid';

    public function __construct(private Ledger $ledger) {}

    /** Beban dibayar dari akun kas/bank/Midtrans, atau ditalangi pemilik (paid_by = capital|loan|reimbursement). */
    public function expense(string $date, string $expenseCode, float $amount, string $paidFrom, string $description, ?int $by = null, ?string $evidence = null, ?string $key = null, ?string $costCenter = null): Entry
    {
        $credit = match ($paidFrom) {
            self::PAYABLE => '2000',
            self::PREPAID => '1400',
            default => self::OWNER_FUNDING[$paidFrom] ?? $paidFrom,
        };

        return $this->ledger->post($date, $description, [[$expenseCode, $amount, 0, null, $costCenter], [$credit, 0, $amount]], 'manual', $key, null, $by, $evidence);
    }

    public function ownerCapital(string $date, float $amount, string $toAccount, string $description = 'Setoran modal pemilik', ?int $by = null): Entry
    {
        return $this->ledger->post($date, $description, [[$toAccount, $amount, 0], ['3000', 0, $amount]], 'manual', null, null, $by);
    }

    public function ownerDrawing(string $date, float $amount, string $fromAccount, string $description = 'Penarikan pemilik (prive)', ?int $by = null): Entry
    {
        return $this->ledger->post($date, $description, [['3100', $amount, 0], [$fromAccount, 0, $amount]], 'manual', null, null, $by);
    }

    /** Pindah dana antar akun milik Yoka sendiri: bukan pendapatan, bukan beban. */
    public function transfer(string $date, float $amount, string $from, string $to, string $description = 'Pindah dana', ?int $by = null): Entry
    {
        if ($from === $to) {
            throw new InvalidArgumentException('Akun asal dan tujuan sama.');
        }

        return $this->ledger->post($date, $description, [[$to, $amount, 0], [$from, 0, $amount]], 'manual', null, null, $by);
    }

    public function otherIncome(string $date, float $amount, string $toAccount, string $revenueCode, string $description, ?int $by = null): Entry
    {
        return $this->ledger->post($date, $description, [[$toAccount, $amount, 0], [$revenueCode, 0, $amount]], 'manual', null, null, $by);
    }

    /**
     * Pembayaran langganan sukses di Midtrans: pendapatan diakui, uangnya masih tertahan di Midtrans.
     * Idempoten per langganan, jadi webhook ganda tidak mencatat pendapatan dua kali.
     */
    public function subscriptionPaid(BusinessSubscription $sub): ?Entry
    {
        $amount = (float) $sub->amount;
        if ($amount <= 0 || $sub->status !== 'active' || ! $sub->paid_at) {
            return null;
        }

        $months = RevenueRecognition::termMonths($sub);
        if ($months <= 1) {
            return $this->ledger->post($sub->paid_at, 'Langganan dibayar via Midtrans (langganan #'.$sub->id.')',
                [['1200', $amount, 0], ['4000', 0, $amount]], 'midtrans', 'midtrans:subscription:'.$sub->id, 'SUB-'.$sub->id);
        }

        // Masa layanan > 1 bulan: diterima di muka, diakui merata per bulan.
        $entry = $this->ledger->post($sub->paid_at, "Langganan {$months} bulan dibayar di muka via Midtrans (langganan #{$sub->id})",
            [['1200', $amount, 0], ['2400', 0, $amount]], 'midtrans', 'midtrans:subscription:'.$sub->id, 'SUB-'.$sub->id);
        $recognition = app(RevenueRecognition::class);
        $recognition->schedule($sub);
        $recognition->recognizeDue();

        return $entry;
    }

    /**
     * Top-up kredit Tanya Yoka lunas via Midtrans. Diakui langsung sebagai pendapatan kredit AI:
     * nilainya kecil dan kredit umumnya habis dalam hitungan minggu. Idempoten per top-up.
     */
    public function aiCreditsPaid(AiCreditTopup $topup): ?Entry
    {
        $amount = (float) $topup->amount;
        if ($amount <= 0 || $topup->status !== 'paid' || ! $topup->paid_at) {
            return null;
        }

        return $this->ledger->post($topup->paid_at, 'Top-up kredit AI '.$topup->credits.' kredit via Midtrans (top-up #'.$topup->id.')',
            [['1200', $amount, 0], ['4200', 0, $amount]], 'midtrans', 'midtrans:ai-credits:'.$topup->id, $topup->payment_token);
    }

    /** Pencairan (settlement) dari Midtrans ke bank, dengan fee yang dipotong. */
    public function midtransPayout(string $date, float $grossSettled, float $fee, string $bankCode = '1100', ?string $reference = null, ?int $by = null): Entry
    {
        $net = round($grossSettled - $fee, 2);
        $lines = [[$bankCode, $net, 0, 'Dana cair'], ['1200', 0, $grossSettled, 'Keluar dari saldo Midtrans']];
        if ($fee > 0) {
            $lines[] = ['5200', $fee, 0, 'Fee Midtrans'];
        }

        return $this->ledger->post($date, 'Pencairan Midtrans'.($reference ? ' '.$reference : ''), $lines, 'manual',
            $reference ? 'midtrans:payout:'.$reference : null, $reference, $by);
    }

    /** Refund ke pelanggan: mengurangi pendapatan (akun kontra), uang keluar dari akun yang dipakai. */
    public function refund(string $date, float $amount, string $fromAccount, string $description, ?int $by = null): Entry
    {
        return $this->ledger->post($date, $description, [['4800', $amount, 0], [$fromAccount, 0, $amount]], 'manual', null, null, $by);
    }
}
