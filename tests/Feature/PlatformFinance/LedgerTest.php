<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Line;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * Akuntansi keuangan (Principles of Accounting Vol. 1). Nomor di nama tes = nomor di brief bagian 9.
 * Skenario mengikuti kasus nyata Yoka: modal pemilik, iklan Meta, Midtrans, server, refund.
 */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    private Transactions $tx;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->ledger = app(Ledger::class);
        $this->tx = app(Transactions::class);
    }

    public function test_01_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ledger->post('2026-11-01', 'salah', [['6000', 500000, 0], ['1100', 0, 400000]]);
    }

    public function test_01b_single_line_and_two_sided_lines_are_rejected(): void
    {
        foreach ([[['6000', 1000, 0]], [['6000', 1000, 1000], ['1100', 0, 0]], [['6000', -5, 0], ['1100', 0, -5]]] as $lines) {
            try {
                $this->ledger->post('2026-11-01', 'salah', $lines);
                $this->fail('Harus ditolak');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, Entry::count());
            }
        }
    }

    public function test_02_journal_posting_stores_balanced_lines(): void
    {
        $e = $this->tx->expense('2026-11-02', '6000', 500000, '1100', 'Iklan Meta', null, 'INV-META-1');
        $this->assertCount(2, $e->lines);
        $this->assertSame(500000.0, (float) $e->lines->sum('debit'));
        $this->assertSame(500000.0, (float) $e->lines->sum('credit'));
    }

    public function test_03_ledger_balances_follow_normal_balance(): void
    {
        $this->tx->ownerCapital('2026-11-01', 5000000, '1100');
        $this->tx->expense('2026-11-02', '6000', 500000, '1100', 'Iklan Meta');
        $b = $this->ledger->balances();
        $this->assertSame(4500000.0, $b['1100']['balance']);
        $this->assertSame(500000.0, $b['6000']['balance']);
        $this->assertSame(5000000.0, $b['3000']['balance']);
    }

    public function test_04_trial_balance_is_balanced(): void
    {
        $this->seedMonth();
        $tb = $this->ledger->trialBalance();
        $this->assertTrue($tb['balanced']);
        $this->assertSame($tb['total_debit'], $tb['total_credit']);
    }

    public function test_05_owner_capital_is_equity_not_revenue(): void
    {
        $this->tx->ownerCapital('2026-11-01', 5000000, '1100');
        $is = $this->ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(0.0, $is['net_revenue']);
        $this->assertSame(5000000.0, $this->ledger->balanceSheet()['total_equity']);
    }

    public function test_06_owner_paid_expense_is_still_a_yoka_expense(): void
    {
        $this->tx->expense('2026-11-03', '6000', 300000, 'reimbursement', 'Iklan Meta pakai kartu pribadi');
        $this->tx->expense('2026-11-03', '5000', 200000, 'capital', 'Hosting dibayar pemilik sebagai modal');
        $this->tx->expense('2026-11-03', '5300', 150000, 'loan', 'Domain dipinjami pemilik');
        $b = $this->ledger->balances();
        $this->assertSame(300000.0, $b['2200']['balance'], 'Talangan = Yoka berhutang ke pemilik.');
        $this->assertSame(200000.0, $b['3000']['balance']);
        $this->assertSame(150000.0, $b['2100']['balance']);
        $this->assertSame(0.0, $b['1100']['balance'], 'Kas Yoka tidak berkurang.');
        $this->assertSame(-650000.0, $this->ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))['net_income']);
    }

    public function test_07_09_midtrans_payment_fee_and_payout_do_not_double_count_revenue(): void
    {
        $this->ledger->post('2026-11-05', 'Langganan', [['1200', 199000, 0], ['4000', 0, 199000]], 'midtrans', 'midtrans:subscription:1');
        $this->tx->midtransPayout('2026-11-07', 199000, 1393, '1100', 'STL-001');
        $b = $this->ledger->balances();
        $is = $this->ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(199000.0, $is['net_revenue'], 'Pencairan bukan pendapatan kedua.');
        $this->assertSame(1393.0, $b['5200']['balance'], 'Fee Midtrans = beban.');
        $this->assertSame(0.0, $b['1200']['balance'], 'Saldo tertahan habis setelah cair.');
        $this->assertSame(197607.0, $b['1100']['balance']);
    }

    public function test_10_duplicate_webhook_and_duplicate_payout_reference_post_once(): void
    {
        $first = $this->ledger->post('2026-11-05', 'Langganan', [['1200', 199000, 0], ['4000', 0, 199000]], 'midtrans', 'midtrans:subscription:9');
        $again = $this->ledger->post('2026-11-05', 'Langganan', [['1200', 199000, 0], ['4000', 0, 199000]], 'midtrans', 'midtrans:subscription:9');
        $this->tx->midtransPayout('2026-11-07', 199000, 1393, '1100', 'STL-9');
        $this->tx->midtransPayout('2026-11-07', 199000, 1393, '1100', 'STL-9');
        $this->assertSame($first->id, $again->id);
        $this->assertSame(2, Entry::count());
    }

    public function test_11_refund_reduces_revenue_and_reversal_keeps_audit_trail(): void
    {
        $this->ledger->post('2026-11-05', 'Langganan', [['1100', 199000, 0], ['4000', 0, 199000]]);
        $this->tx->refund('2026-11-06', 199000, '1100', 'Refund toko batal');
        $is = $this->ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(0.0, $is['net_revenue']);
        $this->assertSame(199000.0, $is['refunds']);

        $wrong = $this->tx->expense('2026-11-07', '6100', 90000, '1100', 'Salah: harusnya Meta');
        $rev = $this->ledger->reverse($wrong, 'Salah akun');
        $this->assertSame($wrong->id, $rev->reversal_of_id);
        $this->assertSame($rev->id, $wrong->fresh()->reversed_by_id);
        $this->assertSame(0.0, $this->ledger->balances()['6100']['balance']);
        $this->assertSame(3 + 1, Entry::count(), 'Jurnal asli tetap ada.');

        $this->expectException(InvalidArgumentException::class);
        $this->ledger->reverse($wrong->fresh(), 'dua kali');
    }

    public function test_posted_records_cannot_be_edited_or_deleted(): void
    {
        $e = $this->tx->expense('2026-11-02', '6000', 500000, '1100', 'Iklan Meta');
        foreach ([fn () => $e->update(['description' => 'diubah']), fn () => $e->delete(), fn () => Line::first()->update(['debit' => 1]), fn () => Line::first()->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Harus ditolak');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('Iklan Meta', $e->fresh()->description);
    }

    public function test_12_monthly_income_statement(): void
    {
        $this->seedMonth();
        $is = $this->ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(995000.0, $is['net_revenue']);         // 5 x 199.000
        $this->assertSame(1100000.0 + 6965.0, $is['cost_of_revenue']); // server 800k + AI 300k + fee
        $this->assertSame(500000.0, $is['marketing']);
        $this->assertSame(150000.0, $is['operating_expenses']);
        $this->assertSame(round(995000 - 1106965 - 500000 - 150000, 2), $is['net_income']);
    }

    public function test_13_balance_sheet_balances_with_current_earnings(): void
    {
        $this->seedMonth();
        $bs = $this->ledger->balanceSheet();
        $this->assertTrue($bs['balanced']);
        $this->assertSame($bs['total_assets'], round($bs['total_liabilities'] + $bs['total_equity'], 2));
    }

    public function test_14_cash_flow_classifies_activities_and_ignores_internal_transfers(): void
    {
        $this->seedMonth();
        $this->tx->transfer('2026-11-15', 100000, '1100', '1000', 'Ambil kas kecil');
        $cf = $this->ledger->cashFlow(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(10000000.0, $cf['financing'], 'Modal pemilik = pendanaan.');
        $this->assertSame($cf['closing_cash'], $this->ledger->cashBalance(Carbon::parse('2026-11-30')));
        $this->assertSame(round($cf['operating'] + $cf['investing'] + $cf['financing'], 2), $cf['net_change']);
    }

    public function test_29_closed_period_is_locked_and_corrections_go_to_current_period(): void
    {
        Carbon::setTestNow('2026-12-10 10:00:00');
        $old = $this->tx->expense('2026-11-02', '6000', 500000, '1100', 'Iklan Meta');
        $this->ledger->closePeriod('2026-11');
        try {
            $this->tx->expense('2026-11-20', '6000', 1, '1100', 'telat');
            $this->fail('Periode tertutup harus menolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah ditutup', $e->getMessage());
        }
        $rev = $this->ledger->reverse($old, 'koreksi');
        $this->assertSame('2026-12-10', $rev->entry_date->toDateString());
    }

    public function test_30_review_flags_unreconciled_midtrans_missing_evidence_and_duplicates(): void
    {
        $this->ledger->post('2026-11-01', 'Langganan', [['1200', 199000, 0], ['4000', 0, 199000]], 'midtrans', 'midtrans:subscription:77');
        $this->tx->expense('2026-11-18', '6000', 250000, '1100', 'Meta A');
        $this->tx->expense('2026-11-18', '6000', 250000, '1100', 'Meta A lagi');
        $issues = collect(app(\App\Services\PlatformFinance\Review::class)->run()['findings'])->pluck('issue');
        $this->assertContains('Saldo Midtrans belum direkonsiliasi', $issues);
        $this->assertContains('Transaksi tanpa bukti', $issues);
        $this->assertContains('Kemungkinan transaksi dobel', $issues);
    }

    public function test_30b_payout_larger_than_payments_makes_midtrans_negative_and_is_flagged(): void
    {
        $this->tx->midtransPayout('2026-11-07', 199000, 0, '1100', 'STL-X');
        $issues = collect(app(\App\Services\PlatformFinance\Review::class)->run()['findings'])->pluck('issue');
        $this->assertContains('Saldo Midtrans negatif', $issues);
    }

    public function test_28_ai_cost_estimate_is_not_booked_as_expense(): void
    {
        app(\App\Services\PlatformAiOperations::class)->ensureAgents();
        $agent = \App\Models\PlatformAiAgent::where('key', 'finance')->firstOrFail();
        \App\Models\PlatformAiRun::create(['platform_ai_agent_id' => $agent->id, 'operation' => 'chat', 'model' => 'x', 'status' => 'ok', 'estimated_cost_usd' => 3.5]);
        $this->assertSame(0.0, $this->ledger->balances()['5100']['balance']);
        $issues = collect(app(\App\Services\PlatformFinance\Review::class)->run()['findings'])->pluck('issue');
        $this->assertContains('Biaya AI belum dicatat dari invoice', $issues);
    }

    /** Bulan contoh: modal 10jt, 5 toko bayar via Midtrans, cair, server, AI, iklan, domain. */
    private function seedMonth(): void
    {
        $this->tx->ownerCapital('2026-11-01', 10000000, '1100');
        foreach (range(1, 5) as $i) {
            $this->ledger->post('2026-11-0'.$i, 'Langganan', [['1200', 199000, 0], ['4000', 0, 199000]], 'midtrans', 'midtrans:subscription:'.$i);
        }
        $this->tx->midtransPayout('2026-11-10', 995000, 6965, '1100', 'STL-NOV');
        $this->tx->expense('2026-11-03', '5000', 800000, '1100', 'VPS', null, 'INV-1');
        $this->tx->expense('2026-11-12', '5100', 300000, '1100', 'OpenAI', null, 'INV-2');
        $this->tx->expense('2026-11-14', '6000', 500000, '1100', 'Meta Ads', null, 'INV-3');
        $this->tx->expense('2026-11-15', '5300', 150000, '1100', 'Domain', null, 'INV-4');
    }
}
