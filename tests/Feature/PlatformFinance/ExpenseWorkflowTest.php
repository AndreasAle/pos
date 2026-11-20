<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Expense;
use App\Services\PlatformAiOperations;
use App\Services\PlatformFinance\AgentFinanceTools;
use App\Services\PlatformFinance\ExpenseWorkflow;
use App\Services\PlatformFinance\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/** Finance V2: alur biaya dengan persetujuan, bukti, anti-dobel, audit, dan batas agen AI. */
class ExpenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        Storage::fake('local');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function wf(): ExpenseWorkflow
    {
        return app(ExpenseWorkflow::class);
    }

    private function meta(array $over = []): array
    {
        return $over + ['expense_date' => '2026-11-18', 'account_code' => '6000', 'paid_from' => '1100', 'amount' => 300000, 'description' => 'Meta Ads November'];
    }

    public function test_submitted_expense_does_not_touch_the_ledger_until_approved(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $this->assertSame('submitted', $x->status);
        $this->assertSame(0, Entry::count());

        $posted = $this->wf()->approveAndPost($x, $this->owner);
        $this->assertSame('posted', $posted->status);
        $this->assertTrue($posted->self_approved, 'Pemilik tunggal menyetujui sendiri: ditandai, bukan disembunyikan.');
        $this->assertSame(300000.0, app(Ledger::class)->balances()['6000']['balance']);
        $this->assertSame(['expense.submitted', 'expense.approved_posted'], AuditEvent::orderBy('id')->pluck('action')->all());
    }

    public function test_double_approval_posts_once(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $this->wf()->approveAndPost($x, $this->owner);
        $this->wf()->approveAndPost($x->fresh(), $this->owner);
        $this->assertSame(1, Entry::count());
    }

    public function test_rejected_expense_never_posts_and_cannot_be_approved_later(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $this->wf()->reject($x, $this->owner, 'Bukan biaya Yoka');
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->wf()->approveAndPost($x->fresh(), $this->owner);
        } finally {
            $this->assertSame(0, Entry::count());
            $this->assertSame('Bukan biaya Yoka', AuditEvent::where('action', 'expense.rejected')->value('reason'));
        }
    }

    public function test_duplicate_invoice_from_same_vendor_is_blocked_but_resubmit_after_rejection_is_allowed(): void
    {
        $vendor = \App\Models\PlatformFinance\Vendor::create(['name' => 'Meta']);
        $first = $this->wf()->submit($this->meta(['vendor_id' => $vendor->id, 'invoice_no' => 'FB-123']), 'admin', $this->owner->id);
        try {
            $this->wf()->submit($this->meta(['vendor_id' => $vendor->id, 'invoice_no' => 'FB-123']), 'admin', $this->owner->id);
            $this->fail('Invoice dobel harus ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah pernah dicatat', $e->getMessage());
        }
        $this->wf()->reject($first, $this->owner, 'salah nominal');
        $this->assertSame('submitted', $this->wf()->submit($this->meta(['vendor_id' => $vendor->id, 'invoice_no' => 'FB-123', 'amount' => 310000]), 'admin', $this->owner->id)->status);
    }

    public function test_invalid_inputs_are_rejected(): void
    {
        foreach ([
            ['amount' => 0], ['account_code' => '4000'], ['paid_from' => '9999'], ['expense_date' => '2026-12-01'],
        ] as $bad) {
            try {
                $this->wf()->submit($this->meta($bad), 'admin', $this->owner->id);
                $this->fail('Harus ditolak: '.json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->assertSame(0, Expense::count());
            }
        }
    }

    public function test_closed_period_rejects_new_expense(): void
    {
        app(Ledger::class)->closePeriod('2026-10');
        $this->expectException(InvalidArgumentException::class);
        $this->wf()->submit($this->meta(['expense_date' => '2026-10-15']), 'admin', $this->owner->id);
    }

    public function test_evidence_upload_is_private_validated_and_downloadable_only_by_finance(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id, UploadedFile::fake()->create('invoice-meta.pdf', 120, 'application/pdf'));
        $this->assertStringStartsWith('finance-evidence/2026/11/', $x->evidence_path);
        Storage::disk('local')->assertExists($x->evidence_path);

        try {
            $this->wf()->submit($this->meta(['description' => 'skrip']), 'admin', $this->owner->id, UploadedFile::fake()->create('evil.php', 1, 'text/x-php'));
            $this->fail('File non-dokumen harus ditolak');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, Expense::count());
        }

        $support = PlatformAdmin::create(['name' => 'S', 'email' => 's@yoka.test', 'password' => 'password-kuat', 'role' => 'support', 'is_active' => true]);
        $this->actingAs($support, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])
            ->get(route('platform.finance.expenses.evidence', $x))->assertForbidden();
        $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])
            ->get(route('platform.finance.expenses.evidence', $x))->assertOk();
    }

    public function test_only_active_finance_admins_can_approve(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $support = PlatformAdmin::create(['name' => 'S', 'email' => 's@yoka.test', 'password' => 'password-kuat', 'role' => 'support', 'is_active' => true]);
        $this->expectException(InvalidArgumentException::class);
        $this->wf()->approveAndPost($x, $support);
    }

    public function test_audit_events_are_append_only(): void
    {
        $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $event = AuditEvent::first();
        $this->expectException(LogicException::class);
        $event->update(['reason' => 'hapus jejak']);
    }

    public function test_nanda_can_only_draft_and_luna_cannot_draft(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        $nanda = PlatformAiAgent::where('key', 'accounting')->firstOrFail();
        $luna = PlatformAiAgent::where('key', 'finance')->firstOrFail();
        $tools = app(AgentFinanceTools::class);

        $this->assertFalse($tools->draftExpense($luna, $this->meta())['ok']);
        $r = $tools->draftExpense($nanda, $this->meta(['vendor' => 'Meta', 'paid_from' => 'reimbursement']));
        $this->assertTrue($r['ok']);
        $this->assertSame('submitted', Expense::find($r['draft_id'])->status);
        $this->assertSame('agent', Expense::find($r['draft_id'])->created_by_type);
        $this->assertSame(0, Entry::count(), 'Draf AI tidak pernah masuk buku besar sendiri.');
        $this->assertFalse($tools->draftExpense($nanda, $this->meta(['amount' => -5]))['ok']);

        foreach (range(1, 4) as $i) {
            $tools->draftExpense($nanda, $this->meta(['description' => 'draf '.$i]));
        }
        $this->assertStringContainsString('Batas 5 draf', $tools->draftExpense($nanda, $this->meta(['description' => 'ke-6']))['error']);

        $posted = $this->wf()->approveAndPost(Expense::find($r['draft_id']), $this->owner);
        $this->assertFalse($posted->self_approved, 'Draf AI disetujui manusia, bukan persetujuan sendiri.');
        $this->assertSame(300000.0, app(Ledger::class)->balances()['2200']['balance'], 'Talangan pemilik tercatat.');
    }

    public function test_finance_page_shows_pending_queue_and_approves_via_http(): void
    {
        $x = $this->wf()->submit($this->meta(), 'admin', $this->owner->id);
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Menunggu persetujuan')->assertSee('Meta Ads November');
        $as->post(route('platform.finance.expenses.approve', $x))->assertRedirect();
        $this->assertSame('posted', $x->fresh()->status);
    }
}
