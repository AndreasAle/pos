<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentProvider;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\SelfOrderService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrOrderController extends Controller
{
    public function index()
    {
        $orders = Order::forBusiness(auth()->user()->business_id)->where('order_source', 'qr_table')
            ->with(['items.addons', 'diningTable.area', 'outlet'])->latest()->paginate(40);

        return view('qr-orders.index', compact('orders'));
    }

    public function accept(Order $order, SelfOrderService $service)
    {
        $this->own($order);
        $service->accept($order, auth()->user());

        return back()->with('success', 'Pesanan diterima dan dikirim ke dapur.');
    }

    public function reject(Request $request, Order $order)
    {
        $this->own($order);
        $request->validate(['reason' => 'nullable|string|max:255']);
        abort_if($order->acceptance_status !== 'pending', 422);
        abort_if($order->payment_status === 'paid', 422, 'Pesanan yang sudah dibayar tidak dapat ditolak.');
        $order->update(['acceptance_status' => 'rejected', 'status' => 'cancelled', 'cancel_reason' => $request->reason ?: 'Ditolak staf']);

        return back()->with('success', 'Pesanan ditolak.');
    }

    public function pay(Request $request, Order $order, PaymentProvider $payments)
    {
        $this->own($order);
        $data = $request->validate(['payment_method' => 'required|in:cash,qris,debit,transfer,ewallet,other', 'paid_amount' => 'required|numeric|min:0']);
        abort_if($order->acceptance_status !== 'accepted', 422, 'Terima pesanan terlebih dahulu.');
        abort_if($order->payment_status === 'paid', 422, 'Pesanan sudah lunas.');
        if ($data['payment_method'] === 'qris') {
            try {
                $payment = $payments->createQris($order);
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['payment' => 'QRIS belum dapat dibuat. Periksa konfigurasi BayarPro.']);
            }
            $order->update([
                'payment_method' => 'qris',
                'payment_gateway' => 'bayarpro',
                'gateway_reference' => $payment['reference'],
                'payment_token' => $payment['qr_string'],
                'qris_url' => $payment['qr_url'] ?: $payment['checkout_url'],
                'payment_expires_at' => $payment['expires_at'] ?: now()->addMinutes(15),
                'payment_status' => 'pending',
            ]);

            return back()->with('success', 'QRIS dibuat. Status lunas menunggu konfirmasi otomatis BayarPro.');
        }
        abort_if((float) $data['paid_amount'] < (float) $order->grand_total, 422, 'Nominal pembayaran kurang.');
        DB::transaction(function () use ($order, $data) {
            $shift = auth()->user()->activeShift()->first();
            $order->update(['user_id' => auth()->id(), 'cashier_shift_id' => $shift?->id, 'payment_method' => $data['payment_method'], 'paid_amount' => $data['paid_amount'], 'change_amount' => max(0, $data['paid_amount'] - $order->grand_total), 'payment_status' => 'paid', 'status' => 'paid']);
            OrderPayment::create(['order_id' => $order->id, 'payment_method' => $data['payment_method'], 'amount' => $order->grand_total, 'reference' => 'QR table cashier']);
        });

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    private function own(Order $order): void
    {
        abort_if($order->business_id !== auth()->user()->business_id || $order->order_source !== 'qr_table', 403);
    }

    public function paymentQr(Order $order)
    {
        $this->own($order);
        abort_if(blank($order->payment_token), 404);
        $result = (new Builder(writer: new SvgWriter, data: $order->payment_token, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 420, margin: 12))->build();

        return response($result->getString())->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'no-store');
    }
}
