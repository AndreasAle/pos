<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
