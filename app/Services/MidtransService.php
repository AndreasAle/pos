<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\CoreApi;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized  = config('midtrans.is_sanitized');
        Config::$is3ds        = config('midtrans.is_3ds');
    }

    public function isConfigured(): bool
    {
        return !empty(config('midtrans.server_key')) && $this->keyMatchesMode();
    }

    /**
     * Key sandbox Midtrans diawali "SB-". Key sandbox dengan MIDTRANS_IS_PRODUCTION=true
     * membuat checkout gagal di Midtrans, jadi checkout dimatikan saja sampai .env benar.
     */
    public function keyMatchesMode(): bool
    {
        // Hanya kasus pasti yang diblok: mode production dengan key sandbox.
        return ! ((bool) config('midtrans.is_production') && str_starts_with((string) config('midtrans.server_key'), 'SB-'));
    }

    /**
     * Create dynamic QRIS transaction (Core API).
     * Returns ['qr_string' => ..., 'transaction_id' => ...]
     */
    public function createQris(string $orderId, int $amount, string $customerName, string $customerEmail = ''): array
    {
        $params = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'qris' => ['acquirer' => 'gopay'],
            'customer_details' => [
                'first_name' => $customerName,
                'email'      => $customerEmail ?: 'pelanggan@fnbpos.com',
            ],
        ];

        $response = CoreApi::charge($params);

        return [
            'transaction_id' => $response->transaction_id ?? null,
            'qr_string'      => $response->qr_string ?? null,
            'qr_url'         => $response->actions[0]->url ?? null,
            'expiry_time'    => $response->expiry_time ?? null,
        ];
    }

    /**
     * Create Snap payment token for subscription.
     * Returns ['token' => ..., 'redirect_url' => ...]
     */
    public function createSnapToken(string $orderId, int $amount, string $customerName, string $customerEmail, string $itemName = 'Langganan Yoka POS 30 hari'): array
    {
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $customerName,
                'email'      => $customerEmail,
            ],
            'item_details' => [[
                'id' => 'YOKA-SUBSCRIPTION',
                'price' => $amount,
                'quantity' => 1,
                'name' => mb_substr($itemName, 0, 50),
            ]],
            'callbacks' => [
                'finish' => route('saas.plans'),
            ],
            'credit_card' => ['secure' => true],
        ];

        // Snap::createTransaction returns object with token + redirect_url (single API call)
        $result = Snap::createTransaction($params);

        return [
            'token'        => $result->token ?? null,
            'redirect_url' => $result->redirect_url ?? null,
        ];
    }

    /**
     * Check transaction status from Midtrans.
     */
    public function checkStatus(string $orderId): ?object
    {
        try {
            return Transaction::status($orderId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify notification signature from Midtrans webhook.
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signature): bool
    {
        $key = (string) config('midtrans.signature_secret');
        if ($key === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $key);
        return hash_equals($expected, $signature);
    }
}
