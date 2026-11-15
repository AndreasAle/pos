<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentProvider;
use App\Exceptions\PosTransactionException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TableSession;
use App\Services\SelfOrderService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;

class SelfOrderController extends Controller
{
    public function menu(Request $request, string $token, SelfOrderService $service)
    {
        $table = DiningTable::where('public_token', $token)->where('is_active', true)->with(['business', 'outlet', 'area'])->firstOrFail();
        $session = $service->openSession($table);
        $categories = ProductCategory::forBusiness($table->business_id)->active()->orderBy('sort_order')->get();
        $products = Product::forBusiness($table->business_id)->active()->forOutletOrGlobal($table->outlet_id)
            ->with(['variants' => fn ($q) => $q->where('is_active', true), 'addons' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->get();

        return view('self-order.menu', compact('table', 'session', 'categories', 'products'));
    }

    public function place(Request $request, string $token, SelfOrderService $service, PaymentProvider $payments)
    {
        $table = DiningTable::where('public_token', $token)->where('is_active', true)->firstOrFail();
        $data = $request->validate([
            'session_token' => 'required|string', 'guest_name' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:1000',
            'payment_choice' => 'required|in:cashier,qris', 'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer', 'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.variant_id' => 'nullable|integer', 'items.*.addons' => 'nullable|array', 'items.*.notes' => 'nullable|string|max:255',
        ]);
        $session = TableSession::where('guest_token', $data['session_token'])->where('dining_table_id', $table->id)->where('status', 'open')->firstOrFail();
        try {
            $order = $service->place($session, $data);
            $payment = null;
            if ($data['payment_choice'] === 'qris') {
                try {
                    $payment = $payments->createQris($order);
                    $order->update(['payment_gateway' => 'bayarpro', 'gateway_reference' => $payment['reference'], 'payment_token' => $payment['qr_string'], 'qris_url' => $payment['qr_url'] ?: $payment['checkout_url'], 'payment_expires_at' => $payment['expires_at'] ?: now()->addMinutes(15)]);
                } catch (\Throwable $e) {
                    $order->delete();
                    report($e);

                    return response()->json(['message' => 'Pembayaran QRIS sedang tidak tersedia. Pilih bayar di kasir atau coba lagi.'], 502);
                }
            }
        } catch (PosTransactionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['order_number' => $order->order_number, 'total' => (float) $order->grand_total, 'status_url' => route('self-order.status', [$session->guest_token, $order->order_number]), 'payment' => $payment]);
    }

    public function status(string $sessionToken, string $orderNumber)
    {
        $session = TableSession::where('guest_token', $sessionToken)->firstOrFail();
        $order = Order::where('table_session_id', $session->id)->where('order_number', $orderNumber)->with('items.addons')->firstOrFail();

        return view('self-order.status', compact('session', 'order'));
    }

    public function paymentQr(string $sessionToken, string $orderNumber)
    {
        $session = TableSession::where('guest_token', $sessionToken)->firstOrFail();
        $order = Order::where('table_session_id', $session->id)->where('order_number', $orderNumber)->firstOrFail();
        abort_if(blank($order->payment_token), 404);
        $result = (new Builder(writer: new SvgWriter, data: $order->payment_token, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 420, margin: 12))->build();

        return response($result->getString())->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'no-store');
    }
}
