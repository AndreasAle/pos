<?php

namespace App\Http\Controllers;

use App\Models\BusinessSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Services\BalanceService;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function __construct(
        protected MidtransService $midtrans,
        protected BalanceService $balanceService,
    ) {}

    // ── QRIS ──────────────────────────────────────────────────────────────────

    /**
     * Generate dynamic QRIS for a POS order.
     * Called via AJAX from POS when Midtrans is configured.
     */
    public function createQris(Request $request)
    {
        if (!$this->midtrans->isConfigured()) {
            return response()->json(['error' => 'Midtrans belum dikonfigurasi.'], 422);
        }

        $request->validate([
            'order_id'    => 'required|integer|exists:orders,id',
            'grand_total' => 'required|numeric|min:1',
        ]);

        $order = Order::findOrFail($request->order_id);
        abort_if($order->business_id !== auth()->user()->business_id, 403);

        try {
            $result = $this->midtrans->createQris(
                'QRIS-' . $order->order_number,
                (int) round($request->grand_total),
                auth()->user()->business->name
            );

            $order->update([
                'payment_token' => $result['transaction_id'],
                'qris_url'      => $result['qr_url'],
            ]);

            return response()->json([
                'qr_url'         => $result['qr_url'],
                'qr_string'      => $result['qr_string'],
                'transaction_id' => $result['transaction_id'],
                'expiry_time'    => $result['expiry_time'],
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans QRIS error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal membuat QRIS: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Poll QRIS payment status from Midtrans.
     */
    public function checkQrisStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|integer|exists:orders,id']);
        $order = Order::findOrFail($request->order_id);
        abort_if($order->business_id !== auth()->user()->business_id, 403);

        if (!$order->payment_token) {
            return response()->json(['status' => 'pending']);
        }

        $txId = 'QRIS-' . $order->order_number;
        $status = $this->midtrans->checkStatus($txId);

        if (!$status) {
            return response()->json(['status' => 'pending']);
        }

        $txStatus = $status->transaction_status ?? 'pending';

        if (in_array($txStatus, ['capture', 'settlement'])) {
            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid', 'status' => 'paid']);
                try {
                    $this->balanceService->creditFromQris($order->load('business'));
                } catch (\Exception $e) {
                    Log::error('Balance credit failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }
            return response()->json(['status' => 'paid']);
        }

        if (in_array($txStatus, ['deny', 'cancel', 'expire'])) {
            return response()->json(['status' => 'failed']);
        }

        return response()->json(['status' => 'pending']);
    }

    // ── SaaS Subscription ─────────────────────────────────────────────────────

    /**
     * Create Midtrans Snap payment for subscription.
     */
    public function createSubscriptionPayment(SubscriptionPlan $plan)
    {
        if (!$this->midtrans->isConfigured()) {
            // Fallback: activate manually (old behavior)
            return $this->activateManually($plan);
        }

        $business = auth()->user()->business;
        $orderId  = 'SUB-' . $business->id . '-' . time();

        try {
            $result = $this->midtrans->createSnapToken(
                $orderId,
                (int) $plan->price,
                $business->name,
                auth()->user()->email ?? 'owner@fnbpos.com'
            );

            if (empty($result['redirect_url'])) {
                throw new \Exception('Midtrans tidak mengembalikan URL pembayaran.');
            }

            BusinessSubscription::create([
                'business_id'          => $business->id,
                'subscription_plan_id' => $plan->id,
                'starts_at'            => today(),
                'ends_at'              => today()->addDays(30),
                'status'               => 'pending',
                'payment_token'        => $orderId,
                'payment_url'          => $result['redirect_url'],
                'notes'                => 'Midtrans payment pending',
            ]);

            return redirect($result['redirect_url']);
        } catch (\Exception $e) {
            Log::warning('Midtrans subscription error, falling back to manual', ['error' => $e->getMessage()]);
            return $this->activateManually($plan, 'Midtrans error: ' . $e->getMessage(), true);
        }
    }

    private function activateManually(SubscriptionPlan $plan, string $notes = 'Manual activation', bool $midtransError = false)
    {
        $business = auth()->user()->business;

        BusinessSubscription::create([
            'business_id'          => $business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => today(),
            'ends_at'              => today()->addDays(30),
            'status'               => 'active',
            'paid_at'              => now(),
            'notes'                => $notes,
        ]);

        $message = 'Paket ' . $plan->name . ' berhasil diaktifkan.';
        if ($midtransError) {
            $message .= ' (Midtrans Snap belum aktif di akun Production — diaktifkan manual. Hubungi support@midtrans.com untuk mengaktifkan Snap.)';
        }

        return redirect()->route('saas.current')->with('success', $message);
    }

    /**
     * Webhook callback from Midtrans.
     * Handles both QRIS order payments and subscription payments.
     */
    public function callback(Request $request)
    {
        $data = $request->all();
        Log::info('Midtrans callback', $data);

        $orderId     = $data['order_id'] ?? '';
        $statusCode  = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';
        $signature   = $data['signature_key'] ?? '';

        if (!$this->midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning('Midtrans invalid signature', compact('orderId'));
            return response()->json(['status' => 'invalid signature'], 400);
        }

        $txStatus = $data['transaction_status'] ?? '';

        // ── Subscription payment ──────────────────────────────────────────────
        if (str_starts_with($orderId, 'SUB-')) {
            $sub = BusinessSubscription::where('payment_token', $orderId)->first();
            if ($sub && in_array($txStatus, ['capture', 'settlement'])) {
                $sub->update(['status' => 'active', 'paid_at' => now()]);
            } elseif ($sub && in_array($txStatus, ['deny', 'cancel', 'expire'])) {
                $sub->update(['status' => 'cancelled']);
            }
            return response()->json(['status' => 'ok']);
        }

        // ── QRIS order payment ────────────────────────────────────────────────
        if (str_starts_with($orderId, 'QRIS-')) {
            $orderNumber = substr($orderId, 5); // remove 'QRIS-'
            $order = Order::with('business')->where('order_number', $orderNumber)->first();
            if ($order && in_array($txStatus, ['capture', 'settlement'])) {
                if ($order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid', 'status' => 'paid']);
                    // Credit merchant balance
                    try {
                        $this->balanceService->creditFromQris($order);
                    } catch (\Exception $e) {
                        Log::error('Balance credit failed for QRIS', ['order' => $orderNumber, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
