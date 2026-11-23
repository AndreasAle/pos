<?php

namespace Tests\Feature\PlatformAi;

use App\Jobs\DraftWhatsAppReply;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiCommercialProposal;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiWhatsAppMessage;
use App\Models\SubscriptionPlan;
use App\Services\PlatformAgents\ProspectDesk;
use App\Services\PlatformAgents\Sales\CommercialPolicy;
use App\Services\PlatformAgents\Sales\HardwareCatalog;
use App\Services\PlatformAgents\Sales\ObjectionClassifier;
use App\Services\PlatformAgents\Sales\OpeningStrategy;
use App\Services\PlatformAgents\Sales\ResponseQuality;
use App\Services\PlatformAgents\Sales\SalesOrchestrator;
use App\Services\PlatformAgents\Sales\SupportPolicy;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kesiapan pilot Adit 2.1: memori lead, pembuka, fallback, support, perangkat, kunjungan lapangan, coalescing & race. */
class AditReadinessTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAiProspect $p;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-22 02:00:00');
        PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
        app(PlatformAiOperations::class)->ensureAgents();
        SubscriptionPlan::create(['name' => 'Starter', 'slug' => 'starter', 'price' => 99000, 'max_outlets' => 2, 'max_users' => 10, 'features' => [], 'is_active' => true, 'sort_order' => 1]);
        $this->p = app(ProspectDesk::class)->check(new PlatformAiProspect([
            'business_name' => 'andopos', 'contact_name' => 'Andopos', 'phone' => '081532963501', 'business_type' => 'Pecel Lele', 'city' => 'Palembang', 'outlet_count' => 1, 'source' => 'wa', 'status' => 'baru',
        ]));
        $this->p->update(['status' => 'dihubungi', 'contact_count' => 1, 'sales_stage' => 'outreach_sent']);
    }

    private function turn(string $text, int $id): array
    {
        app(ProspectDesk::class)->inbound($this->p->phone_normalized, $text);

        return app(SalesOrchestrator::class)->onInbound($this->p->fresh(), $text, $id);
    }

    private function ai(string $reply): void
    {
        config(['services.yoka_ai.key' => 'k', 'services.yoka_ai.url' => 'https://ai.test/v1', 'services.yoka_fonnte.webhook_secret' => 'rahasia-uji', 'services.yoka_fonnte.token' => 'tok', 'yoka_sales.bd21.reply_lock_wait' => 1]);
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(
                ['relevant' => true, 'intent' => 'lainnya', 'emotion' => 'netral', 'needs_human' => false, 'reply' => $reply, 'closing' => false, 'summary' => 'uji']
            )]]]]),
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);
    }

    private function sentCount(): int
    {
        return collect(Http::recorded())->filter(fn ($x) => str_contains($x[0]->url(), 'fonnte'))->count();
    }

    // ---- Memori lead (akar masalah: typo "kerta") ----

    public function test_typo_paper_is_remembered_and_not_asked_again(): void
    {
        $this->turn('saya pakek kerta sih masih kak, kenapa', 1);
        $p = $this->p->fresh();
        $this->assertSame('kertas', $p->intelligence['facts']['current_method']['value']);
        $this->assertNotSame('current_method', $p->intelligence['next_question']['key'] ?? null);
        $this->assertStringNotContainsString('catat penjualannya pakai apa', (string) $p->intelligence['qualification']['next_action']);
        $this->assertNotContains('current_method', $p->intelligence['qualification']['missing']);
    }

    public function test_margin_report_is_not_a_confidential_probe(): void
    {
        $c = app(ObjectionClassifier::class);
        $this->assertNotContains('CONFIDENTIAL_PROBE', ObjectionClassifier::types($c->classify('kasir ramai sih kak, sama laporan margin dll gitu')));
        $this->assertNotContains('CONFIDENTIAL_PROBE', ObjectionClassifier::types($c->classify('modal saya terbatas')));
        $this->assertContains('CONFIDENTIAL_PROBE', ObjectionClassifier::types($c->classify('margin kalian berapa sih dari langganan ini?')));
        $this->assertContains('CONFIDENTIAL_PROBE', ObjectionClassifier::types($c->classify('boleh lihat data toko lain?')));
    }

    // ---- Pembuka ----

    public function test_opening_is_short_and_natural(): void
    {
        $m = app(OpeningStrategy::class)->opening($this->p)['message'];
        $this->assertStringStartsWith('Selamat pagi Pak/Bu', $m);
        $this->assertStringContainsString('Saya Adit dari Yoka POS', $m);
        $this->assertStringContainsString('aplikasi kasir untuk usaha kuliner', $m);
        $this->assertStringContainsString('kalau warung sedang ramai', $m);
        $this->assertStringNotContainsStringIgnoringCase('andopos', $m);
        $this->assertSame(1, substr_count($m, '?'));
        $this->assertLessThan(300, mb_strlen($m));
    }

    // ---- Kualitas & fallback ----

    public function test_filtered_reply_stays_coherent_and_falls_back_when_needed(): void
    {
        $q = app(ResponseQuality::class);
        $s = ['objective' => 'DISCOVER_NEED', 'allow_question' => true, 'allow_trial_cta' => false];
        $r = $q->review('Tim kami respon cepat 24 jam, dan', $this->p, $s);
        $this->assertSame('', $r['text']);
        $this->assertContains('no_safe_reply', $r['issues']);

        $price = ['objective' => 'ANSWER_DIRECT_QUESTION', 'allow_question' => true, 'allow_trial_cta' => true];
        $r = $q->review('Harganya Rp50.000 dijamin paling murah.', $this->p, $price);
        $this->assertStringContainsString('Starter Rp 99.000/bulan', $r['text']);
        $this->assertContains('fallback_used', $r['issues']);

        $r = $q->review('oke, jadi', $this->p, $s);
        $this->assertSame('Oke.', $r['text']);

        $r = $q->review('Bisa banget. Usahanya jualan apa saja?', $this->p, $s, ['Oke kak. Usahanya jualan apa saja?']);
        $this->assertSame('Bisa banget.', $r['text']);
        $this->assertContains('repeated_question', $r['issues']);
    }

    // ---- Support ----

    public function test_support_policy_is_truthful_when_hours_unset_and_allows_configured_hours(): void
    {
        $this->assertStringContainsString('BELUM ditetapkan', SupportPolicy::promptLine());
        $this->assertStringContainsString('bukan tim support manusia', SupportPolicy::promptLine());
        $s = ['objective' => 'ANSWER_DIRECT_QUESTION', 'allow_question' => true, 'allow_trial_cta' => false];
        $this->assertSame('', app(ResponseQuality::class)->review('Support kami 24 jam nonstop.', $this->p, ['objective' => 'DISCOVER_NEED'] + $s)['text']);

        config(['yoka_support.human_hours' => 'Senin-Sabtu 09.00-17.00 WIB']);
        $this->assertStringContainsString('Senin-Sabtu 09.00-17.00 WIB', SupportPolicy::promptLine());
        $this->assertStringContainsString('Senin-Sabtu', app(ResponseQuality::class)->review('Tim manusia ada Senin-Sabtu 09.00-17.00 WIB, pasti dibantu.', $this->p, $s)['text']);
    }

    // ---- Perangkat ----

    public function test_candidate_printer_is_never_quoted_and_bundle_becomes_quotation_request(): void
    {
        $this->assertNull(app(HardwareCatalog::class)->approvedBundle());
        $this->assertStringContainsString('Belum ada perangkat atau bundel resmi', app(HardwareCatalog::class)->promptFacts());
        $r = $this->turn('kalau aplikasi sama printer sekalian berapa kak?', 2);
        $this->assertStringNotContainsString('166', $r['context']);
        $this->assertStringNotContainsString('349', $r['context']);
        $prop = PlatformAiCommercialProposal::sole();
        $this->assertSame('hardware_quotation', $prop->analysis['kind']);
        $this->assertStringContainsString('MP-58N', $prop->analysis['candidate_item']);
        $this->assertContains('warranty', $prop->analysis['missing_terms']);
    }

    public function test_approved_bundle_can_be_quoted_without_approval(): void
    {
        config(['yoka_hardware.items.mp-58n.status' => 'approved', 'yoka_hardware.items.mp-58n.compatibility' => 'verified', 'yoka_hardware.items.mp-58n.selling_price' => 349000,
            'yoka_hardware.bundles.basic-3-58.status' => 'approved',
            'yoka_hardware.bundles.basic-3-58.terms' => ['shipping' => 'ongkir ditanggung pembeli', 'installation' => 'setup jarak jauh', 'warranty' => '3 bulan', 'returns' => '7 hari', 'refunds' => 'sesuai kebijakan', 'taxes' => 'termasuk']]);
        $e = app(CommercialPolicy::class)->evaluate('aplikasi sama printer sekalian berapa?');
        $this->assertSame(CommercialPolicy::STANDARD_OFFER, $e['state']);
        $this->assertSame(699000.0, $e['official_price']);
        [$clean, $removed] = app(CommercialPolicy::class)->scrubReply('YokaBasic 3 bulan + printer Rp699.000. Printer saja Rp349.000. Paket 12 bulan + printer Rp1.649.000.');
        $this->assertStringContainsString('Rp699.000', $clean);
        $this->assertStringContainsString('Rp349.000', $clean);
        $this->assertStringNotContainsString('Rp1.649.000', $clean, 'Bundel yang masih draft tidak boleh disebut.');
        $this->assertCount(1, $removed);
    }

    // ---- Kunjungan lapangan ----

    public function test_explicit_visit_consent_creates_one_field_brief(): void
    {
        $this->turn('masih pakai kertas, kasir sering ramai', 3);
        $this->turn('harganya berapa?', 4);
        $this->assertSame(0, PlatformAiTask::where('type', 'field_visit_brief')->count(), 'Tanya harga bukan izin kunjungan.');

        $r = $this->turn('boleh kak, datang aja ke warung saya buat demo', 5);
        $this->assertSame('ARRANGE_FIELD_VISIT', $r['strategy']['objective']);
        $this->assertNotNull($r['escalation']);
        $this->assertSame('ready_for_field_visit', $this->p->fresh()->sales_stage);
        $task = PlatformAiTask::where('type', 'field_visit_brief')->sole();
        $this->assertSame('kertas', $task->payload['brief']['cara_catat_sekarang']);
        $this->assertStringContainsString('antrean kasir', $task->payload['brief']['masalah']);
        $this->assertFalse((bool) $task->requires_approval);
        $this->assertStringContainsString('JANGAN menyebut jam', $r['context']);

        $this->turn('mau lihat printernya langsung juga', 6);
        $this->assertSame(1, PlatformAiTask::where('type', 'field_visit_brief')->count());
    }

    // ---- Coalescing, race, duplikat, gateway gagal ----

    public function test_coalescing_delays_via_queue_but_stop_is_immediate(): void
    {
        config(['services.yoka_fonnte.webhook_secret' => 'rahasia-uji', 'yoka_sales.bd21.coalesce_seconds' => 8]);
        Bus::fake();
        $this->post('/platform-ai/whatsapp/webhook/rahasia-uji', ['sender' => '6281532963501', 'message' => 'berapa harganya?', 'inboxid' => 'C1'])->assertOk();
        Bus::assertDispatched(DraftWhatsAppReply::class, fn ($job) => $job->delay !== null);
        $this->post('/platform-ai/whatsapp/webhook/rahasia-uji', ['sender' => '6281532963501', 'message' => 'stop jangan hubungi lagi', 'inboxid' => 'C2'])->assertOk();
        Bus::assertDispatchedAfterResponse(DraftWhatsAppReply::class);
    }

    public function test_burst_out_of_order_duplicate_and_lock_never_double_send(): void
    {
        $this->ai('Starter Rp99.000/bulan.');
        $a = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6281532963501', 'body' => 'berapa harganya?']);
        $b = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6281532963501', 'body' => 'untuk satu outlet']);
        (new DraftWhatsAppReply($b->id))->handle(); // job pesan terbaru jalan duluan (out of order)
        (new DraftWhatsAppReply($a->id))->handle(); // job lama: sudah ada pesan lebih baru -> diam
        (new DraftWhatsAppReply($b->id))->handle(); // duplikat
        $this->assertSame(1, $this->sentCount());

        $c = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6281532963501', 'body' => 'kalau 2 outlet?']);
        Carbon::setTestNow(); // Lock::block menghitung waktu dengan now(): jam tidak boleh dibekukan saat menunggu
        $lock = Cache::lock('adit-reply:6281532963501', 30);
        $lock->get();
        (new DraftWhatsAppReply($c->id))->handle(); // balasan lain sedang berjalan -> tidak tumpang tindih
        $this->assertSame(1, $this->sentCount());
        $lock->release();
    }

    public function test_gateway_failure_records_nothing_as_sent(): void
    {
        config(['services.yoka_ai.key' => 'k', 'services.yoka_ai.url' => 'https://ai.test/v1', 'services.yoka_fonnte.token' => 'tok', 'yoka_sales.bd21.reply_lock_wait' => 1]);
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['relevant' => true, 'reply' => 'Starter Rp99.000/bulan.', 'summary' => 'x'])]]]]),
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device offline'], 500),
        ]);
        $m = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6281532963501', 'body' => 'berapa harganya?']);
        (new DraftWhatsAppReply($m->id))->handle();
        $this->assertSame(0, PlatformAiWhatsAppMessage::where('direction', 'outbound')->count());
    }

    // ---- Harness evaluasi ----

    public function test_live_evaluation_harness_refuses_without_key_and_rolls_back(): void
    {
        config(['services.yoka_ai.key' => null]);
        $this->artisan('adit:evaluate')->assertFailed();

        $this->ai('Baik, terima kasih.');
        $before = PlatformAiProspect::count();
        $this->artisan('adit:evaluate', ['--only' => [1, 7, 13]])->assertSuccessful();
        $this->assertSame($before, PlatformAiProspect::count(), 'Data evaluasi di-rollback.');
        $this->assertSame(0, PlatformAiWhatsAppMessage::where('phone', 'like', '628000009%')->count());
    }
}
