<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentProvider;
use App\Models\Order;
use Illuminate\Http\Request;

class BayarProWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentProvider $provider)
    {
        abort_unless($provider->verifyWebhook($request->getContent(), $request->headers->all()), 401);
        $event = $provider->parseWebhook($request->all());
        $order = Order::where('order_number', $event['order_number'])->where('gateway_reference', $event['reference'])->firstOrFail();
        if ($event['paid'] && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'paid', 'status' => 'paid', 'paid_amount' => $order->grand_total]);
        }

        return response()->json(['received' => true]);
    }
}
