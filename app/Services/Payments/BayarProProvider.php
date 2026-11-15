<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BayarProProvider implements PaymentProvider
{
    public function createQris(Order $order): array
    {
        $key = (string) config('services.bayarpro.server_key');
        $merchant = (string) config('services.bayarpro.merchant_id');
        if ($key === '' || $merchant === '') {
            throw new RuntimeException('Kredensial BayarPro belum dikonfigurasi.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.bayarpro.url'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(15)
            ->post('/v1/payment/create', [
                'merchant_id' => $merchant,
                'order_id' => $order->order_number,
                'payment_method' => 'QRIS',
                'amount' => (int) round((float) $order->grand_total),
                'callback_url' => route('payments.bayarpro.webhook'),
                'customer_name' => $order->hold_name ?: 'Pelanggan Yoka',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('BayarPro tidak dapat membuat pembayaran.');
        }

        $data = $response->json();

        return [
            'reference' => data_get($data, 'data.reference_id', data_get($data, 'reference_id')),
            'qr_url' => data_get($data, 'data.qr_url', data_get($data, 'qr_url')),
            'qr_string' => data_get($data, 'data.qr_string', data_get($data, 'qr_string')),
            'checkout_url' => data_get($data, 'data.checkout_url', data_get($data, 'checkout_url')),
            'expires_at' => data_get($data, 'data.expires_at', data_get($data, 'expires_at')),
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string) config('services.bayarpro.webhook_secret');
        if ($secret === '') {
            return false;
        }
        $provided = $headers['x-bayarpro-signature'][0] ?? $headers['x-signature'][0] ?? '';
        if ($provided === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'order_number' => (string) data_get($payload, 'data.order_id', data_get($payload, 'order_id', '')),
            'reference' => (string) data_get($payload, 'data.reference_id', data_get($payload, 'reference_id', '')),
            'paid' => in_array(strtolower((string) data_get($payload, 'data.status', data_get($payload, 'status', ''))), ['paid', 'success', 'settlement'], true),
        ];
    }
}
