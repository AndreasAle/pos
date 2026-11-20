<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\Period;
use App\Services\PlatformFinance\ExpenseWorkflow;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\MonthEndClose;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/** Finance V2: checklist tutup buku dan buka ulang terkontrol. */
class MonthEndCloseTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function close(): MonthEndClose
    {
        return app(MonthEndClose::class);
    }

    private function itemStatus(array $check, string $key): string
    {
        return collect($check['items'])->firstWhere('key', $key)['status'];
    }

    public function test_current_period_cannot_be_closed(): void
    {
        $check = $this->close()->checklist('2026-11');
        $this->assertSame('fail', $this->itemStatus($check, 'period_ended'));
        $this->expectException(InvalidArgumentException::class);
        $this->close()->close('2026-11', $this->owner, true);
    }

    public function test_pending_expense_blocks_close_until_decided(): void
    {
        $x = app(ExpenseWorkflow::class)->submit(['expense_date' => '2026-10-10', 'account_code' => '5000', 'paid_from' => '1100', 'amount' => 150000, 'description' => 'Hosting'], 'admin', $this->owner->id);
        $this->assertSame('fail', $this->itemStatus($this->close()->checklist('2026-10'), 'pending_expenses'));
        try {
            $this->close()->close('2026-10', $this->owner, true);
            $this->fail('Harus diblokir');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('menunggu persetujuan', $e->getMessage());
        }
        app(ExpenseWorkflow::class)->approveAndPost($x, $this->owner);
        $this->assertTrue($this->close()->checklist('2026-10')['can_close']);
    }

    public function test_warnings_require_acknowledgement_and_are_snapshotted(): void
    {
        app(Transactions::class)->expense('2026-10-05', '6000', 300000, '1100', 'Meta Ads tanpa bukti', $this->owner->id);
        $check = $this->close()->checklist('2026-10');
        $this->assertTrue($check['can_close']);
        $this->assertSame('warn', $this->itemStatus($check, 'evidence'));
        $this->assertSame('warn', $this->itemStatus($check, 'next_budget'));

        try {
            $this->close()->close('2026-10', $this->owner);
            $this->fail('Peringatan harus ditinjau');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tinjau', $e->getMessage());
        }

        $row = $this->close()->close('2026-10', $this->owner, true);
        $this->assertTrue($row->warnings_acknowledged);
        $this->assertSame(-300000.0, (float) $row->checklist['net_income']);
        $this->assertTrue(app(Ledger::class)->isClosed(Carbon::parse('2026-10-15')));
        $this->assertSame(['evidence', 'next_budget'], array_values(array_intersect(['evidence', 'next_budget'], AuditEvent::where('action', 'period.closed')->first()->after['warnings'])));
    }

    public function test_clean_period_closes_without_acknowledgement(): void
    {
        Budget::create(['period' => '2026-11', 'platform_fin_account_id' => \App\Models\PlatformFinance\Account::where('code', '6000')->value('id'), 'amount' => 500000]);
        $this->assertFalse($this->close()->checklist('2026-10')['has_warnings']);
        $this->assertFalse($this->close()->close('2026-10', $this->owner)->warnings_acknowledged);
    }

    public function test_reopen_is_super_admin_only_needs_reason_and_latest_period_only(): void
    {
        foreach (['2026-09', '2026-10'] as $p) {
            $this->close()->close($p, $this->owner, true);
        }
        $finance = PlatformAdmin::create(['name' => 'F', 'email' => 'f@yoka.test', 'password' => 'password-kuat', 'role' => 'finance', 'is_active' => true]);

        foreach ([[$finance, '2026-10', 'salah input hosting'], [$this->owner, '2026-10', 'salah'], [$this->owner, '2026-09', 'koreksi biaya september']] as [$who, $p, $why]) {
            try {
                $this->close()->reopen($p, $who, $why);
                $this->fail("Harus ditolak: {$p} {$why}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(app(Ledger::class)->isClosed(Carbon::parse($p.'-15')));
            }
        }

        $row = $this->close()->reopen('2026-10', $this->owner, 'Invoice Meta Oktober terlambat datang');
        $this->assertSame(1, $row->reopen_count);
        $this->assertFalse(app(Ledger::class)->isClosed(Carbon::parse('2026-10-15')));
        app(Transactions::class)->expense('2026-10-28', '6000', 100000, '1100', 'Meta Oktober susulan', $this->owner->id, 'INV-1');
        $this->assertSame('Invoice Meta Oktober terlambat datang', AuditEvent::where('action', 'period.reopened')->value('reason'));

        $again = $this->close()->close('2026-10', $this->owner, true);
        $this->assertNull($again->reopened_at);
        $this->assertSame(2, Period::count(), 'Satu baris per periode; riwayat buka-tutup ada di audit.');
    }

    public function test_page_shows_checklist_and_closes_via_http(): void
    {
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Tutup buku bulanan')->assertSee('Neraca saldo seimbang');
        $as->post(route('platform.finance.close'), ['period' => '2026-10', 'acknowledge' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(app(Ledger::class)->isClosed(Carbon::parse('2026-10-01')));
        $as->post(route('platform.finance.reopen'), ['period' => '2026-10', 'reason' => 'Koreksi pencatatan hosting'])->assertSessionHasNoErrors();
        $this->assertFalse(app(Ledger::class)->isClosed(Carbon::parse('2026-10-01')));
    }
}
