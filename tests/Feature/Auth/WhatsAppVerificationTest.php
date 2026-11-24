<?php

namespace Tests\Feature\Auth;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PlatformWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pemilik yang mendaftar dari aplikasi Android memverifikasi nomor WhatsApp
 * dengan kode 6 digit. Batas keras: berlaku 5 menit, 5x salah, jeda 60 detik,
 * 5 kode per nomor per jam. Email tetap tersedia sebagai cadangan.
 */
class WhatsAppVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const APP_UA = 'Mozilla/5.0 (Linux; Android 14) Chrome/130 Mobile YokaPOSApp/1.0.1';

    /** @var array<int, array{phone: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->app->instance(PlatformWhatsApp::class, new class($test) extends PlatformWhatsApp {
            public function __construct(private $test) {}
            public function send(string $phone, string $message, bool $typing = false, int $delay = 2): array
            {
                $this->test->record($phone, $message);
                return ['ok' => true];
            }
        });
    }

    public function record(string $phone, string $message): void
    {
        $this->sent[] = compact('phone', 'message');
    }

    private function newOwner(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'business_id' => null, 'outlet_id' => null, 'role' => 'owner', 'is_active' => true,
            'requires_business_setup' => true, 'phone' => '081234567890', 'email_verified_at' => null,
        ], $attrs));
    }

    private function lastCode(): string
    {
        preg_match('/\*(\d{6})\*/', end($this->sent)['message'], $m);

        return $m[1];
    }

    public function test_app_signup_goes_to_whatsapp_and_sends_one_code(): void
    {
        $owner = $this->newOwner();

        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)
            ->get('/email/verify')->assertRedirect(route('verification.whatsapp'));

        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)
            ->get('/email/whatsapp')->assertOk()->assertSee('Cek WhatsApp kamu')->assertSee('+62 8123 4567 890');

        $this->assertCount(1, $this->sent);
        $this->assertSame('6281234567890', $this->sent[0]['phone']);
        $this->assertDatabaseMissing('phone_otps', ['code_hash' => $this->lastCode()]);

        // Membuka ulang halaman tidak mengirim kode lagi.
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/email/whatsapp')->assertOk();
        $this->assertCount(1, $this->sent);
    }

    public function test_browser_signup_keeps_email_verification(): void
    {
        $owner = $this->newOwner();

        $this->actingAs($owner)->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130')
            ->get('/email/verify')->assertOk()->assertSee($owner->email);

        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)
            ->get('/email/verify?via=email')->assertOk();
        $this->assertCount(0, $this->sent);
    }

    public function test_correct_code_verifies_and_opens_onboarding(): void
    {
        $owner = $this->newOwner();
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/email/whatsapp');

        $this->actingAs($owner)->post('/email/whatsapp', ['code' => $this->lastCode()])
            ->assertRedirect(route('onboarding.business'));

        $this->assertNotNull($owner->fresh()->phone_verified_at);
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->actingAs($owner->fresh())->get('/mulai/bisnis')->assertOk();
    }

    public function test_wrong_code_locks_after_five_tries(): void
    {
        $owner = $this->newOwner();
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/email/whatsapp');
        $good = $this->lastCode();
        $bad  = $good === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($owner)->post('/email/whatsapp', ['code' => $bad])->assertSessionHas('otp_error');
        }
        // Kode yang benar pun ditolak setelah terkunci.
        $this->actingAs($owner)->post('/email/whatsapp', ['code' => $good])->assertSessionHas('otp_error');
        $this->assertNull($owner->fresh()->phone_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $owner = $this->newOwner();
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/email/whatsapp');
        $code = $this->lastCode();

        Carbon::setTestNow(now()->addMinutes(6));
        $this->actingAs($owner)->post('/email/whatsapp', ['code' => $code])->assertSessionHas('otp_error');
        Carbon::setTestNow();

        $this->assertNull($owner->fresh()->phone_verified_at);
    }

    public function test_resend_waits_sixty_seconds_and_caps_per_hour(): void
    {
        $owner = $this->newOwner();
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/email/whatsapp');

        $this->actingAs($owner)->post('/email/whatsapp/kirim')->assertSessionHas('otp_error');
        $this->assertCount(1, $this->sent);

        for ($i = 1; $i <= 6; $i++) {
            Carbon::setTestNow(now()->addSeconds(61));
            $this->actingAs($owner)->post('/email/whatsapp/kirim');
        }
        Carbon::setTestNow();

        $this->assertCount(5, $this->sent);
        $this->assertSame(5, PhoneOtp::where('user_id', $owner->id)->count());
    }
}
