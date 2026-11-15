<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentProvider
{
    public function createQris(Order $order): array;

    public function verifyWebhook(string $rawBody, array $headers): bool;

    public function parseWebhook(array $payload): array;
}
