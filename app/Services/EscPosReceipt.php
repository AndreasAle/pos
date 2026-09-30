<?php

namespace App\Services;

use App\Models\Order;

/**
 * Renders a receipt as raw ESC/POS bytes for Bluetooth thermal printers.
 *
 * Browsers on Android cannot print to a Bluetooth thermal printer, so the
 * register hands these bytes to the RawBT app instead. Line widths are the
 * printer's character columns in its default font: 32 for 58mm, 48 for 80mm.
 */
class EscPosReceipt
{
    private const INIT        = "\x1B\x40";
    private const ALIGN_LEFT  = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const BOLD_ON     = "\x1B\x45\x01";
    private const BOLD_OFF    = "\x1B\x45\x00";
    private const FEED_CUT    = "\n\n\n\x1D\x56\x41\x00";

    private int $cols;

    public function render(Order $order): string
    {
        $order->loadMissing('items.addons', 'outlet', 'user', 'customer', 'business');

        $settings   = $order->business->settings ?? [];
        $this->cols = ($settings['receipt_size'] ?? '80mm') === '58mm' ? 32 : 48;

        $out = self::INIT . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->wrap($settings['receipt_header'] ?? $order->business->name) . self::BOLD_OFF;

        if ($order->outlet->name !== $order->business->name) {
            $out .= $this->wrap($order->outlet->name);
        }
        if ($order->outlet->address) {
            $out .= $this->wrap($order->outlet->address);
        }
        if ($order->outlet->phone) {
            $out .= $this->wrap('Telp: ' . $order->outlet->phone);
        }

        $out .= self::ALIGN_LEFT . $this->rule();
        $out .= $this->row('No', $order->order_number);
        $out .= $this->row('Tanggal', $order->created_at->format('d/m/Y H:i'));
        $out .= $this->row('Kasir', $order->user->name);
        if ($order->customer) {
            $out .= $this->row('Pelanggan', $order->customer->name);
        }
        $out .= $this->rule();

        foreach ($order->items as $item) {
            $out .= $this->wrap($item->product_name . ($item->variant_name ? ' (' . $item->variant_name . ')' : ''));
            $out .= $this->row('  ' . number_format($item->qty, 0) . ' x ' . $this->money($item->price), $this->money($item->subtotal));
            foreach ($item->addons as $addon) {
                $out .= $this->wrap('  + ' . $addon->addon_name . ' ' . $this->money($addon->price));
            }
            if ($item->notes) {
                $out .= $this->wrap('  * ' . $item->notes);
            }
        }

        $out .= $this->rule();
        $out .= $this->row('Subtotal', $this->money($order->subtotal));
        if ($order->discount_amount > 0) {
            $out .= $this->row('Diskon', '-' . $this->money($order->discount_amount));
        }
        if ($order->tax_amount > 0) {
            $out .= $this->row('Pajak (' . ($settings['tax_percent'] ?? 10) . '%)', $this->money($order->tax_amount));
        }
        if ($order->service_amount > 0) {
            $out .= $this->row('Service', $this->money($order->service_amount));
        }
        $out .= self::BOLD_ON . $this->row('TOTAL', $this->money($order->grand_total)) . self::BOLD_OFF;
        $out .= $this->row(strtoupper($order->payment_method), $this->money($order->paid_amount));
        if ($order->change_amount > 0) {
            $out .= $this->row('Kembalian', $this->money($order->change_amount));
        }
        $out .= $this->rule();

        $out .= self::ALIGN_CENTER . $this->wrap($settings['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!');

        return $out . self::FEED_CUT;
    }

    private function money($amount): string
    {
        return 'Rp' . number_format((float) $amount, 0, ',', '.');
    }

    private function rule(): string
    {
        return str_repeat('-', $this->cols) . "\n";
    }

    /** Label on the left, value flush right; wraps the label if both do not fit. */
    private function row(string $label, string $value): string
    {
        $label = $this->ascii($label);
        $value = $this->ascii($value);
        $room  = $this->cols - mb_strlen($value) - 1;

        if (mb_strlen($label) <= $room) {
            return $label . str_repeat(' ', $this->cols - mb_strlen($label) - mb_strlen($value)) . $value . "\n";
        }

        return $this->wrap($label) . str_pad($value, $this->cols, ' ', STR_PAD_LEFT) . "\n";
    }

    private function wrap(string $text): string
    {
        return wordwrap($this->ascii($text), $this->cols, "\n", true) . "\n";
    }

    /** Printer code pages have no emoji or UTF-8; drop what they cannot draw. */
    private function ascii(string $text): string
    {
        $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return rtrim(preg_replace('/[^\x20-\x7E]/', '', $plain === false ? $text : $plain));
    }
}
