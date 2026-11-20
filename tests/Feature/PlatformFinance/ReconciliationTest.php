<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\StatementLine;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\MonthEndClose;
use App\Services\PlatformFinance\Reconciliation;
use App\Services\PlatformFinance\Review;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/** Finance V2: rekonsiliasi bank/Midtrans per transaksi. */
class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function rc(): Reconciliation
    {
        return app(Reconciliation::class);
    }

    private function books(): void
    {
        $tx = app(Transactions::class);
        $tx->ownerCapital('2026-11-01', 2000000, '1100', 'Setoran modal', $this->owner->id);
        $tx->expense('2026-11-05', '5000', 150000, '1100', 'Hostinger', $this->owner->id, 'INV-H');
        $tx->expense('2026-11-10', '6000', 300000, '1100', 'Meta Ads', $this->owner->id, 'INV-M');
    }

    public function test_parse_amount_handles_indonesian_formats(): void
    {
        foreach (['1.250.000' => 1250000.0, '-150000' => -150000.0, 'Rp 1.250.000,50' => 1250000.5, '1250000.50' => 1250000.5, '(300.000)' => -300000.0, 'abc' => null] as $in => $out) {
            $this->assertSame($out, $this->rc()->parseAmount((string) $in), $in);
        }
    }

    public function test_import_is_idempotent_and_auto_matches_only_exact_amounts(): void
    {
        $this->books();
        $csv = "Tanggal;Keterangan;Nominal;Ref\n01/11/2026;SETORAN;2.000.000;\n06/11/2026;HOSTINGER;-150.000;\n10/11/2026;FACEBOOK ADS;-300.000;\n12/11/2026;BIAYA ADMIN;-6.500;\n12/11/2026;BIAYA ADMIN;-6.500;";
        $r = $this->rc()->import('1100', $csv, $this->owner);
        $this->assertSame(5, $r['imported'], 'Dua baris identik di hari sama tetap dua mutasi.');
        $this->assertSame(3, $this->rc()->autoMatch('1100', $this->owner));

        $again = $this->rc()->import('1100', $csv, $this->owner);
        $this->assertSame([0, 5], [$again['imported'], $again['duplicates']]);
        $this->assertSame(0, Entry::where('description', 'like', '%admin%')->count(), 'Rekonsiliasi tidak pernah membuat jurnal sendiri.');

        $s = $this->rc()->summary('1100', null, 1537000);
        $this->assertSame(1550000.0, $s['ledger_balance']);
        $this->assertSame(-13000.0, $s['not_in_books_net']);
        $this->assertSame(0.0, $s['difference']);
        $this->assertFalse($s['reconciled'], 'Belum rekonsiliasi selama biaya admin belum dijurnal.');
    }

    public function test_ambiguous_candidates_are_left_for_a_human(): void
    {
        $tx = app(Transactions::class);
        $tx->ownerCapital('2026-11-01', 1000000, '1100', 'Modal', $this->owner->id);
        $tx->expense('2026-11-09', '7100', 100000, '1100', 'Tool A', $this->owner->id, 'a');
        $tx->expense('2026-11-11', '7100', 100000, '1100', 'Tool B', $this->owner->id, 'b');
        $this->rc()->import('1100', "2026-11-10,DEBIT,-100000", $this->owner);
        $this->assertSame(0, $this->rc()->autoMatch('1100'));
        $this->assertCount(2, $this->rc()->candidates(StatementLine::first()));
    }

    public function test_manual_match_requires_exact_amount_and_one_to_one(): void
    {
        $this->books();
        $this->rc()->import('1100', "2026-11-06,HOSTINGER,-150000\n2026-11-07,HOSTINGER LAGI,-150000\n2026-11-08,META,-299000", $this->owner);
        [$a, $b, $meta] = StatementLine::orderBy('id')->get()->all();
        $hostLine = $this->rc()->candidates($a)->first()->id;

        $this->rc()->match($a, $hostLine, $this->owner, 'cocok');
        try {
            $this->rc()->match($b, $hostLine, $this->owner);
            $this->fail('Satu jurnal tidak boleh dipakai dua mutasi');
        } catch (InvalidArgumentException) {
        }
        $metaLine = \App\Models\PlatformFinance\Line::where('debit', 0)->where('credit', 300000)->value('id');
        $this->expectException(InvalidArgumentException::class);
        $this->rc()->match($meta, $metaLine, $this->owner);
    }

    public function test_ignore_needs_reason_unmatch_is_audited_and_lines_are_immutable(): void
    {
        $this->rc()->import('1200', "2026-11-15,TEST PAYMENT SANDBOX,10000", $this->owner);
        $s = StatementLine::first();
        try {
            $this->rc()->ignore($s, $this->owner, 'x');
            $this->fail('Alasan wajib');
        } catch (InvalidArgumentException) {
        }
        $this->rc()->ignore($s, $this->owner, 'Transaksi uji sandbox');
        $this->rc()->unmatch($s->fresh(), $this->owner, 'salah klik');
        $this->assertSame(['statement.imported', 'statement.ignored', 'statement.unmatched'], AuditEvent::orderBy('id')->pluck('action')->all());

        $this->expectException(LogicException::class);
        $s->fresh()->update(['amount' => 1]);
    }

    public function test_non_cash_accounts_and_future_rows_are_rejected(): void
    {
        $r = $this->rc()->import('1100', "2026-12-01,NANTI,1000\n2026-11-01,OK,1000", $this->owner);
        $this->assertSame(1, $r['imported']);
        $this->assertNotEmpty($r['errors']);
        $this->expectException(InvalidArgumentException::class);
        $this->rc()->import('4000', "2026-11-01,X,1000", $this->owner);
    }

    public function test_unmatched_lines_flag_review_and_month_end(): void
    {
        $this->rc()->import('1100', "2026-10-20,TRANSFER TAK DIKENAL,500000", $this->owner);
        $this->assertContains('Mutasi rekening belum dicocokkan', array_column(app(Review::class)->run()['findings'], 'issue'));
        $this->assertSame('warn', collect(app(MonthEndClose::class)->checklist('2026-10')['items'])->firstWhere('key', 'statements')['status']);
    }

    public function test_page_imports_and_matches_via_http(): void
    {
        $this->books();
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->post(route('platform.finance.statements.import'), ['account' => '1100', 'csv' => "2026-11-05,HOSTINGER,-150000\n2026-11-12,ADMIN,-6500"])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(1, StatementLine::where('status', 'matched')->count());
        $as->get(route('platform.finance', ['recon' => '1100']))->assertOk()->assertSee('Rekonsiliasi rekening')->assertSee('ADMIN');
        $as->post(route('platform.finance.statements.reconcile', StatementLine::where('status', 'unmatched')->first()), ['action' => 'ignore', 'reason' => 'Biaya admin dicatat bulanan'])->assertSessionHasNoErrors();
        $this->assertSame(0, app(Reconciliation::class)->unmatchedCount());
        $this->assertTrue(app(Ledger::class)->trialBalance()['balanced']);
    }
}
