<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\KitchenTicket;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $order->load('items.addons', 'outlet', 'user', 'customer', 'shift', 'business');
        return view('receipt.show', compact('order'));
    }

    public function print(Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $order->load('items.addons', 'outlet', 'user', 'customer', 'business');
        return view('receipt.print', compact('order'));
    }

    /**
     * The kitchen ticket. The tablet app fetches this page and prints the
     * ESC/POS payload to the kitchen printer; a browser gets the same ticket to
     * print. Orders with nothing for the kitchen get a page without a payload,
     * which the app takes as "nothing to print".
     */
    public function kitchen(Order $order, KitchenTicket $ticket)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $order->load('items.addons', 'items.product.category', 'customer', 'business');

        $items   = KitchenTicket::items($order);
        $escpos  = $ticket->render($order);
        $payload = $escpos === null ? null : 'rawbt:base64,' . base64_encode($escpos);
        $short   = $ticket->shortNumber($order->order_number);

        return view('receipt.kitchen', compact('order', 'items', 'payload', 'short'));
    }

    public function pdf(Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $order->load('items.addons', 'outlet', 'user', 'customer', 'business');

        $settings = $order->business->settings ?? [];
        $paperWidth = ($settings['receipt_size'] ?? '80mm') === '58mm' ? [0, 0, 164.41, 1000] : [0, 0, 226.77, 1000];

        $pdf = Pdf::loadView('receipt.pdf', compact('order'))
            ->setPaper($paperWidth)
            ->setOptions([
                'dpi'         => 96,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        return $pdf->download('struk-' . $order->order_number . '.pdf');
    }
}
