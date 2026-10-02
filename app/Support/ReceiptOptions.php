<?php

namespace App\Support;

use App\Models\Order;

/**
 * What a printed receipt shows, read once from the shop's settings so the
 * browser print view and the ESC/POS (RawBT) renderer stay in step.
 *
 * Every field falls back to something sensible, so a shop that never opened
 * the receipt settings still prints a complete receipt.
 */
class ReceiptOptions
{
    public const TOGGLES = [
        'receipt_show_logo'      => false,
        'receipt_show_address'   => true,
        'receipt_show_phone'     => true,
        'receipt_show_cashier'   => true,
        'receipt_show_customer'  => true,
        'receipt_show_order_type'=> true,
        'receipt_show_total_qty' => true,
    ];

    public static function for(Order $order): array
    {
        $business = $order->business;
        $outlet   = $order->outlet;
        $s        = $business->settings ?? [];

        $flag = fn (string $key) => (bool) ($s[$key] ?? self::TOGGLES[$key]);

        $address = trim((string) ($s['receipt_address'] ?? '')) ?: ($outlet->address ?: $business->address);
        $phone   = trim((string) ($s['receipt_phone'] ?? '')) ?: ($outlet->phone ?: $business->phone);

        return [
            'name'       => trim((string) ($s['receipt_header'] ?? '')) ?: $business->name,
            'subtitle'   => trim((string) ($s['receipt_subtitle'] ?? '')),
            'address'    => $flag('receipt_show_address') ? $address : null,
            'phone'      => $flag('receipt_show_phone') ? $phone : null,
            'logo'       => $flag('receipt_show_logo') && $business->logo ? asset('storage/' . $business->logo) : null,
            'footer'     => trim((string) ($s['receipt_footer'] ?? '')) ?: 'Terima kasih telah berbelanja',
            'cashier'    => $flag('receipt_show_cashier'),
            'customer'   => $flag('receipt_show_customer'),
            'order_type' => $flag('receipt_show_order_type'),
            'retail'     => $business->isRetail(),
            'total_qty'  => $flag('receipt_show_total_qty'),
            'narrow'     => ($s['receipt_size'] ?? '80mm') === '58mm',
            'tax_percent'=> $s['tax_percent'] ?? 10,
        ];
    }

    public static function orderTypeLabel(?string $type, bool $retail = false): ?string
    {
        if ($retail) {
            return match ($type) {
                'delivery' => 'Kirim',
                default    => null,
            };
        }

        return match ($type) {
            'dine_in'  => 'Dine In',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            default    => null,
        };
    }

    public static function paymentLabel(?string $method): string
    {
        return match ($method) {
            'cash'     => 'Tunai',
            'qris'     => 'QRIS',
            'transfer' => 'Transfer',
            'ewallet'  => 'E-Wallet',
            'debit'    => 'Debit',
            default    => ucfirst((string) $method),
        };
    }
}
