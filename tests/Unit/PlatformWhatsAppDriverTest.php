<?php

namespace Tests\Unit;

use App\Services\PlatformWhatsApp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Saklar YOKA_WA_DRIVER: Fonnte tetap default, gateway sendiri hanya dipakai
 * bila diminta, dan alasan penolakan rem anti-spam sampai ke pemanggil.
 */
class PlatformWhatsAppDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.yoka_fonnte.token' => 'tok-fonnte',
            'services.yoka_fonnte.gateway_url' => 'https://wa.gateway.test/',
            'services.yoka_fonnte.gateway_token' => 'tok-gateway',
        ]);
    }

    public function test_default_driver_still_sends_through_fonnte(): void
    {
        config(['services.yoka_fonnte.driver' => null]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

        $this->assertTrue(app(PlatformWhatsApp::class)->send('081234567890', 'halo')['ok']);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send'
            && $r->hasHeader('Authorization', 'tok-fonnte') && $r['target'] === '6281234567890');
    }

    public function test_gateway_driver_posts_fonnte_compatible_payload_to_own_gateway(): void
    {
        config(['services.yoka_fonnte.driver' => 'gateway']);
        Http::fake(['wa.gateway.test/*' => Http::response(['status' => true, 'id' => 'X'])]);

        $this->assertTrue(app(PlatformWhatsApp::class)->send('081234567890', 'halo', true, 3)['ok']);

        Http::assertSent(fn ($r) => $r->url() === 'https://wa.gateway.test/send'
            && $r->hasHeader('Authorization', 'tok-gateway')
            && $r['target'] === '6281234567890' && $r['message'] === 'halo' && (int) $r['delay'] === 3);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'fonnte'));
    }

    public function test_gateway_spam_guard_refusal_reaches_caller(): void
    {
        config(['services.yoka_fonnte.driver' => 'gateway']);
        Http::fake(['wa.gateway.test/*' => Http::response(['status' => false, 'reason' => 'batas per nomor per jam'], 429)]);

        $result = app(PlatformWhatsApp::class)->send('081234567890', 'halo');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('batas per nomor per jam', $result['error']);
    }

    public function test_gateway_driver_without_url_is_not_configured(): void
    {
        config(['services.yoka_fonnte.driver' => 'gateway', 'services.yoka_fonnte.gateway_url' => null]);
        Http::fake();

        $this->assertFalse(PlatformWhatsApp::configured());
        $this->assertFalse(app(PlatformWhatsApp::class)->send('081234567890', 'halo')['ok']);
        Http::assertNothingSent();
    }
}
