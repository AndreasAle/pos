<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $token;
    private string $apiUrl = 'https://api.fonnte.com/send';

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public static function forBusiness(\App\Models\Business $business): ?self
    {
        $token = $business->settings['fonnte_token'] ?? null;
        if (!$token) return null;
        return new self($token);
    }

    public function sendReceipt(Order $order, string $phone): bool
    {
        $message = $this->buildReceiptMessage($order);

        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->post($this->apiUrl, [
                    'target'  => $this->normalizePhone($phone),
                    'message' => $message,
                    'typing'  => false,
                    'delay'   => 1,
                ]);

            if (!$response->successful()) {
                Log::warning('WA receipt failed', ['order' => $order->order_number, 'response' => $response->body()]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WA receipt error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function buildReceiptMessage(Order $order): string
    {
        $business = $order->business;
        $items    = $order->items->map(function ($item) {
            $addons = $item->addons->map(fn($a) => "  + {$a->addon_name} (Rp " . number_format($a->price, 0, ',', '.') . ")")->join("\n");
            $line   = "• {$item->product_name} x{$item->qty} = Rp " . number_format($item->subtotal, 0, ',', '.');
            return $addons ? $line . "\n" . $addons : $line;
        })->join("\n");

        $separator = str_repeat('─', 30);
        $msg       = "🧾 *STRUK PEMBAYARAN*\n";
        $msg      .= "*{$business->name}*\n";
        $msg      .= $separator . "\n";
        $msg      .= "No: *{$order->order_number}*\n";
        $msg      .= "Tanggal: " . $order->created_at->format('d/m/Y H:i') . "\n";
        $msg      .= $separator . "\n";
        $msg      .= $items . "\n";
        $msg      .= $separator . "\n";

        if ($order->discount_amount > 0) {
            $msg .= "Diskon: -Rp " . number_format($order->discount_amount, 0, ',', '.') . "\n";
        }
        if ($order->tax_amount > 0) {
            $msg .= "Pajak: Rp " . number_format($order->tax_amount, 0, ',', '.') . "\n";
        }
        if ($order->delivery_fee > 0) {
            $msg .= "Ongkir: Rp " . number_format($order->delivery_fee, 0, ',', '.') . "\n";
        }

        $msg .= "*TOTAL: Rp " . number_format($order->grand_total, 0, ',', '.') . "*\n";
        $msg .= "Bayar: Rp " . number_format($order->paid_amount, 0, ',', '.') . "\n";

        if ($order->change_amount > 0) {
            $msg .= "Kembali: Rp " . number_format($order->change_amount, 0, ',', '.') . "\n";
        }

        $msg .= $separator . "\n";
        $footer = $business->settings['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!';
        $msg   .= "_" . $footer . "_";

        return $msg;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }
}
