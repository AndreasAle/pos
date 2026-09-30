<?php

namespace App\Services;

use App\Models\Order;
use App\Support\ReceiptOptions;

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
    // Double height only: double width would halve the columns.
    private const TALL_ON     = "\x1D\x21\x01";
    private const TALL_OFF    = "\x1D\x21\x00";
    private const FEED_CUT    = "\n\n\n\x1D\x56\x41\x00";

    private int $cols;

    public function render(Order $order): string
    {
        $order->loadMissing('items.addons', 'outlet', 'user', 'customer', 'business');

        $o          = ReceiptOptions::for($order);
        $this->cols = $o['narrow'] ? 32 : 48;

        // Logos are skipped here: raster images over RawBT are slow and vary
        // by printer. The browser print path shows the logo.
        $out = self::INIT . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . self::TALL_ON . $this->wrap($o['name']) . self::TALL_OFF . self::BOLD_OFF;
        if ($o['subtitle']) {
            $out .= $this->wrap($o['subtitle']);
        }
        if ($o['address']) {
            $out .= $this->wrap($o['address']);
        }
        if ($o['phone']) {
            $out .= $this->wrap('No. Telp ' . $o['phone']);
        }

        $out .= self::ALIGN_LEFT . $this->rule();
        $out .= $this->row($order->created_at->format('d-m-Y'), $o['cashier'] ? $order->user->name : '');
        $out .= $this->row(
            $order->created_at->format('H:i:s'),
            $o['customer'] && $order->customer ? $order->customer->name : ''
        );
        $type = $o['order_type'] ? ReceiptOptions::orderTypeLabel($order->order_type) : null;
        $out .= self::BOLD_ON . $this->row('No. ' . $order->order_number, $type ?? '') . self::BOLD_OFF;
        $out .= $this->rule();

        foreach ($order->items->values() as $i => $item) {
            $name = ($i + 1) . '. ' . $item->product_name . ($item->variant_name ? ' (' . $item->variant_name . ')' : '');
            $out .= self::BOLD_ON . $this->wrap($name) . self::BOLD_OFF;
            $out .= $this->row('   ' . $this->qty($item->qty) . ' x ' . number_format((float) $item->price, 0, ',', '.'), $this->money($item->subtotal));
            foreach ($item->addons as $addon) {
                $out .= $this->wrap('   + ' . $addon->addon_name . ' ' . number_format((float) $addon->price, 0, ',', '.'));
            }
            if ($item->notes) {
                $out .= $this->wrap('   * ' . $item->notes);
            }
        }

        $out .= $this->rule();
        if ($o['total_qty']) {
            $out .= 'Total QTY : ' . $this->qty($order->items->sum('qty')) . "\n\n";
        }
        $out .= $this->row('Sub Total', $this->money($order->subtotal));
        if ($order->discount_amount > 0) {
            $out .= $this->row('Diskon', '-' . $this->money($order->discount_amount));
        }
        if ($order->tax_amount > 0) {
            $out .= $this->row('Pajak (' . $o['tax_percent'] . '%)', $this->money($order->tax_amount));
        }
        if ($order->service_amount > 0) {
            $out .= $this->row('Service', $this->money($order->service_amount));
        }
        if (($order->delivery_fee ?? 0) > 0) {
            $out .= $this->row('Ongkos Kirim', $this->money($order->delivery_fee));
        }
        $out .= self::BOLD_ON . $this->row('Total', $this->money($order->grand_total)) . self::BOLD_OFF;
        $out .= $this->rule();

        $out .= $this->row('Bayar (' . ReceiptOptions::paymentLabel($order->payment_method) . ')', $this->money($order->paid_amount));
        if ($order->change_amount > 0) {
            $out .= self::BOLD_ON . $this->row('Kembalian', $this->money($order->change_amount)) . self::BOLD_OFF;
        }
        $out .= $this->rule();

        $out .= self::ALIGN_CENTER . $this->wrap($o['footer']);

        return $out . self::FEED_CUT;
    }

    private function qty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
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
