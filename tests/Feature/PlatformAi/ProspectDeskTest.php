<?php

namespace Tests\Feature\PlatformAi;

use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiTask;
use App\Services\PlatformAgents\ProspectDesk;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Prospek Adit: data dicek, maks 3 draf per hari, hanya terkirim lewat persetujuan, opt-out dihormati. */
class ProspectDeskTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    private PlatformAiAgent $adit;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 09:00:00');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
        app(PlatformAiOperations::class)->ensureAgents();
        $this->adit = PlatformAiAgent::where('key', 'cs')->firstOrFail();
    }

    private function desk(): ProspectDesk
    {
        return app(ProspectDesk::class);
    }

    private function prospect(array $over = []): PlatformAiProspect
    {
        return $this->desk()->check(new PlatformAiProspect($over + [
            'business_name' => 'Kopi Senja', 'contact_name' => 'budi', 'phone' => '0812 3456 7890', 'business_type' => 'coffee shop',
            'city' => 'bandung', 'source' => 'Kenalan di pameran UMKM', 'status' => 'baru',
        ]));
    }

    private function as()
    {
        return $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_check_normalizes_data_and_marks_ready(): void
    {
        $p = $this->prospect();
        $this->assertSame('6281234567890', $p->phone_normalized);
        $this->assertSame('Kafe', $p->business_type);
        $this->assertSame('Bandung', $p->city);
        $this->assertSame('Budi', $p->contact_name);
        $this->assertSame('siap', $p->status);
    }

    public function test_blocking_issues_keep_prospect_out_of_the_queue(): void
    {
        $this->prospect();
        $dup = $this->prospect(['business_name' => 'Kopi Lain']);
        $this->assertSame('perlu_dicek', $dup->status);
        $this->assertStringContainsString('Nomor sama', $dup->check_issues[0]['text']);

        $this->assertSame('perlu_dicek', $this->prospect(['phone' => '0899111', 'business_name' => 'X'])->status);
        $this->assertSame('perlu_dicek', $this->prospect(['phone' => '081299990000', 'source' => ''])->status);

        $biz = Business::factory()->create(['phone' => '081255554444']);
        $customer = $this->prospect(['phone' => '6281255554444', 'business_name' => 'Sudah pelanggan']);
        $this->assertSame('perlu_dicek', $customer->status);
        $this->assertSame($biz->id, $customer->matched_business_id);
    }

    public function test_at_most_three_drafts_per_day_and_nothing_is_sent(): void
    {
        foreach (range(1, 5) as $i) {
            $this->prospect(['phone' => '08123456780'.$i, 'business_name' => "Kopi {$i}"]);
        }
        $this->assertCount(3, $this->desk()->draftToday($this->adit));
        $this->assertCount(0, $this->desk()->draftToday($this->adit), 'Batas harian dikunci di kode.');
        $this->assertSame(3, PlatformAiTask::where('type', 'whatsapp_draft')->where('status', 'proposed')->count());
        $this->assertSame(3, PlatformAiProspect::where('status', 'dijadwalkan')->count());
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->first();
        $this->assertStringContainsString('Kopi', $task->summary);
        $this->assertStringContainsString('yokapos.com/register', $task->summary);
        $this->assertSame('prospect_opening', $task->payload['kind']);
    }

    public function test_approve_and_send_marks_contacted_then_one_followup_only(): void
    {
        config(['services.whatsapp_gateway.url' => null]);
        $p = $this->prospect();
        $this->desk()->draftToday($this->adit);
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->firstOrFail();
        $this->desk()->markSent($task);
        $p->refresh();
        $this->assertSame(['dihubungi', 1], [$p->status, $p->contact_count]);

        Carbon::setTestNow('2026-11-22 09:00:00');
        $this->assertCount(0, $this->desk()->draftToday($this->adit), 'Belum 3 hari: belum follow-up.');
        Carbon::setTestNow('2026-11-24 09:30:00');
        $made = $this->desk()->draftToday($this->adit);
        $this->assertSame('prospect_followup', $made[0]['kind']);
        $this->desk()->markSent(PlatformAiTask::where('payload->kind', 'prospect_followup')->firstOrFail());
        Carbon::setTestNow('2026-11-30 09:30:00');
        $this->assertCount(0, $this->desk()->draftToday($this->adit), 'Maksimal 2 kali dihubungi.');
    }

    public function test_reply_and_stop_request_are_respected(): void
    {
        $p = $this->prospect();
        $this->desk()->draftToday($this->adit);
        $this->desk()->markSent(PlatformAiTask::firstOrFail());

        $this->desk()->inbound('6281234567890', 'halo, harganya berapa?');
        $this->assertSame('membalas', $p->fresh()->status);
        $this->assertStringContainsString('PROSPEK', $this->desk()->context($p->fresh()));

        $this->desk()->inbound('6281234567890', 'maaf ga minat, jangan hubungi lagi ya');
        $this->assertSame('berhenti', $p->fresh()->status);
        $this->assertNotNull($p->fresh()->opted_out_at);
        Carbon::setTestNow('2026-11-30 09:00:00');
        $this->assertCount(0, $this->desk()->draftToday($this->adit));
    }

    public function test_rejected_draft_returns_prospect_to_queue(): void
    {
        $p = $this->prospect();
        $this->desk()->draftToday($this->adit);
        $task = PlatformAiTask::firstOrFail();
        $this->as()->patch(route('platform.ai-team.decision', $task), ['decision' => 'reject'])->assertRedirect();
        $this->assertSame('siap', $p->fresh()->status);
    }

    public function test_spreadsheet_page_paste_edit_and_delete(): void
    {
        $this->as()->get(route('platform.prospects.index'))->assertOk()->assertSee('Prospek Adit')->assertSee('Tempel dari spreadsheet');

        // "Tambah baris" mengirim baris kosong: harus jadi baris baru berstatus perlu dicek, bukan error.
        $blank = $this->as()->postJson(route('platform.prospects.store'), [])->assertCreated()->json();
        $this->assertSame('perlu_dicek', $blank['status']);
        $this->as()->deleteJson(url('platform/prospects/'.$blank['id']))->assertOk();

        $text = "Nama bisnis\tKontak\tNo WA\tJenis\tKota\tOutlet\tSumber\tCatatan\nBakso Pak Kumis\tKumis\t081311112222\twarung bakso\tdepok\t2\tTetangga\tramai malam\nToko Maju\t\t0813\tretail\tbogor\t\t\t";
        $rows = $this->as()->postJson(route('platform.prospects.paste'), ['text' => $text])->assertOk()->json('rows');
        $this->assertCount(2, $rows);
        $this->assertSame('Restoran', $rows[0]['business_type']);
        $this->assertSame('siap', $rows[0]['status']);
        $this->assertSame('perlu_dicek', $rows[1]['status']);

        $fixed = $this->as()->patchJson(url('platform/prospects/'.$rows[1]['id']), ['phone' => '081377778888', 'source' => 'Datang ke booth'])->assertOk()->json();
        $this->assertSame('siap', $fixed['status']);

        $this->as()->deleteJson(url('platform/prospects/'.$rows[1]['id']))->assertOk();
        $this->assertSame(1, PlatformAiProspect::count());

        $support = PlatformAdmin::create(['name' => 'S', 'email' => 's@yoka.test', 'password' => 'password-kuat', 'role' => 'support', 'is_active' => true]);
        $this->actingAs($support, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])->get(route('platform.prospects.index'))->assertForbidden();
    }
}
