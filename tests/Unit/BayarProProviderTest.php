<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Payments\BayarProProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BayarProProviderTest extends TestCase
{
    public function test_it_creates_a_normalised_qris_invoice(): void
    {
        config()->set('services.bayarpro', [
            'url' => 'https://gateway.test',
            'server_key' => 'server-secret',
            'merchant_id' => 'merchant-1',
            'webhook_secret' => 'webhook-secret',
        ]);
        Http::fake(['gateway.test/*' => Http::response(['data' => [
            'reference_id' => 'REF-1', 'checkout_url' => 'https://pay.test/REF-1', 'expires_at' => '2026-11-15T12:00:00+07:00',
        ]])]);

        $order = new Order(['order_number' => 'SO-001', 'grand_total' => 125000, 'hold_name' => 'Budi']);
        $result = app(BayarProProvider::class)->createQris($order);

        $this->assertSame('REF-1', $result['reference']);
        $this->assertSame('https://pay.test/REF-1', $result['checkout_url']);
        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.test/v1/payment/create'
            && $request['order_id'] === 'SO-001'
            && $request['amount'] === 125000
            && $request['payment_method'] === 'QRIS');
    }

    public function test_webhook_requires_a_matching_hmac(): void
    {
        config()->set('services.bayarpro.webhook_secret', 'webhook-secret');
        $body = '{"order_id":"SO-001","status":"paid"}';
        $signature = hash_hmac('sha256', $body, 'webhook-secret');
        $provider = app(BayarProProvider::class);

        $this->assertTrue($provider->verifyWebhook($body, ['x-signature' => [$signature]]));
        $this->assertFalse($provider->verifyWebhook($body, ['x-signature' => ['forged']]));
        $this->assertFalse($provider->verifyWebhook($body, []));
    }
}
