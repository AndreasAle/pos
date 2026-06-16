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
        return !empty(config('midtrans.server_key'));
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
    public function createSnapToken(string $orderId, int $amount, string $customerName, string $customerEmail): array
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
        $key      = config('midtrans.server_key');
        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $key);
        return hash_equals($expected, $signature);
    }
}
