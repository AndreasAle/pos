<?php

namespace App\Services;

use App\Models\Order;
use App\Support\ReceiptOptions;
use Illuminate\Support\Collection;

/**
 * The slip the kitchen cooks from: only items whose category is marked
 * "Kirim ke dapur", in large print, with variants, add-ons and notes but no
 * prices.
 *
 * Rendered as ESC/POS for the tablet app, which prints it straight to the
 * kitchen printer; receipt/kitchen.blade.php shows the same content for a
 * browser print.
 */
class KitchenTicket
{
    private const INIT         = "\x1B\x40";
    private const ALIGN_LEFT   = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const BOLD_ON      = "\x1B\x45\x01";
    private const BOLD_OFF     = "\x1B\x45\x00";
    // Double height keeps the full line width; double width+height is for the
    // short header only.
    private const TALL_ON      = "\x1D\x21\x01";
    private const BIG_ON       = "\x1D\x21\x11";
    private const SIZE_OFF     = "\x1D\x21\x00";
    private const FEED_CUT     = "\n\n\n\x1D\x56\x41\x00";

    private int $cols;

    /** The order's items that the kitchen makes. */
    public static function items(Order $order): Collection
    {
        $order->loadMissing('items.addons', 'items.product.category');

        return $order->items
            ->filter(fn ($item) => (bool) $item->product?->category?->send_to_kitchen)
            ->values();
    }

    /** ESC/POS bytes, or null when nothing in the order is for the kitchen. */
    public function render(Order $order): ?string
    {
        $items = self::items($order);

        if ($items->isEmpty()) {
            return null;
        }

        $order->loadMissing('customer', 'business');

        $o          = ReceiptOptions::for($order);
        $this->cols = $o['narrow'] ? 32 : 48;

        $out = self::INIT . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . self::BIG_ON . "DAPUR\n" . self::SIZE_OFF . self::BOLD_OFF;

        $type = ReceiptOptions::orderTypeLabel($order->order_type, $o['retail']);
        $out .= self::BOLD_ON . self::TALL_ON
            . $this->ascii('#' . $this->shortNumber($order->order_number) . '  ' . $order->created_at->format('H:i')
                . ($type ? '  ' . $type : ''))
            . "\n" . self::SIZE_OFF . self::BOLD_OFF;

        $out .= self::ALIGN_LEFT;
        if ($order->customer) {
            $out .= $this->wrap('Pelanggan: ' . $order->customer->name);
        }
        $out .= $this->wrap('No. ' . $order->order_number);
        $out .= $this->rule();

        foreach ($items as $item) {
            $name = $this->qty($item->qty) . 'x ' . $item->product_name;
            $out .= self::BOLD_ON . self::TALL_ON . $this->wrap($name) . self::SIZE_OFF . self::BOLD_OFF;

            if ($item->variant_name) {
                $out .= $this->wrap('   (' . $item->variant_name . ')');
            }
            foreach ($item->addons as $addon) {
                $out .= $this->wrap('   + ' . $addon->addon_name);
            }
            if ($item->notes) {
                $out .= self::BOLD_ON . $this->wrap('   * ' . $item->notes) . self::BOLD_OFF;
            }
        }

        $out .= $this->rule();

        if ($order->notes) {
            $out .= self::BOLD_ON . $this->wrap('Catatan: ' . $order->notes) . self::BOLD_OFF;
            $out .= $this->rule();
        }

        return $out . self::FEED_CUT;
    }

    /** "ORD-261105-0006" → "0006": what the kitchen calls out. */
    public function shortNumber(string $orderNumber): string
    {
        $parts = explode('-', $orderNumber);

        return end($parts) ?: $orderNumber;
    }

    private function qty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
    }

    private function rule(): string
    {
        return str_repeat('=', $this->cols) . "\n";
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
