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
use Illuminate\Support\Str;

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
                \App\Services\TelegramNotifier::orderPaid($order);
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
    public function createSubscriptionPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->firstOrFail();

        if (!$this->midtrans->isConfigured()) {
            return redirect()->route('saas.plans')->with(
                'error',
                'Checkout belum tersedia karena kredensial Midtrans belum dipasang.'
            );
        }

        $business = auth()->user()->business;
        $orderId = 'SUB-'.$business->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
        $amount = (int) round((float) $plan->price);
        $current = $business->activeSubscription()->first();
        $periodBase = $current?->ends_at && $current->ends_at->isFuture()
            ? $current->ends_at->copy()
            : today();

        $subscription = BusinessSubscription::create([
            'business_id'          => $business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => today(),
            'ends_at'              => $periodBase->addDays(30),
            'status'               => 'pending',
            'payment_token'        => $orderId,
            'amount'               => $amount,
            'notes'                => 'Menunggu pembayaran Midtrans',
        ]);

        try {
            $result = $this->midtrans->createSnapToken(
                $orderId,
                $amount,
                $business->name,
                auth()->user()->email ?? 'owner@fnbpos.com'
            );

            if (empty($result['redirect_url'])) {
                throw new \Exception('Midtrans tidak mengembalikan URL pembayaran.');
            }

            $subscription->update(['payment_url' => $result['redirect_url']]);

            return redirect()->away($result['redirect_url']);
        } catch (\Exception $e) {
            $subscription->update([
                'status' => 'cancelled',
                'notes' => 'Checkout gagal dibuat. Silakan coba kembali.',
            ]);
            Log::warning('Midtrans subscription checkout failed', [
                'business_id' => $business->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('saas.plans')->with(
                'error',
                'Checkout Midtrans belum berhasil dibuat. Silakan coba kembali.'
            );
        }
    }

    /**
     * Webhook callback from Midtrans.
     * Handles both QRIS order payments and subscription payments.
     */
    public function callback(Request $request)
    {
        $data = $request->all();

        $orderId     = $data['order_id'] ?? '';
        $statusCode  = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';
        $signature   = $data['signature_key'] ?? '';

        Log::info('Midtrans callback received', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'transaction_status' => $data['transaction_status'] ?? '',
        ]);

        if (!$this->midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning('Midtrans invalid signature', compact('orderId'));
            return response()->json(['status' => 'invalid signature'], 400);
        }

        $txStatus = $data['transaction_status'] ?? '';

        // ── Subscription payment ──────────────────────────────────────────────
        if (str_starts_with($orderId, 'SUB-')) {
            $sub = BusinessSubscription::where('payment_token', $orderId)->first();
            if (!$sub) {
                return response()->json(['status' => 'subscription not found'], 404);
            }

            if ($sub->amount === null || (int) round((float) $sub->amount) !== (int) round((float) $grossAmount)) {
                Log::warning('Midtrans subscription amount mismatch', [
                    'subscription_id' => $sub->id,
                    'expected' => $sub->amount,
                    'received' => $grossAmount,
                ]);

                return response()->json(['status' => 'amount mismatch'], 400);
            }

            $paymentSucceeded = $txStatus === 'settlement'
                || ($txStatus === 'capture' && ($data['fraud_status'] ?? '') === 'accept');
            if ($paymentSucceeded) {
                DB::transaction(function () use ($sub) {
                    $locked = BusinessSubscription::query()->lockForUpdate()->findOrFail($sub->id);
                    if ($locked->status === 'active') {
                        return;
                    }

                    BusinessSubscription::query()
                        ->where('business_id', $locked->business_id)
                        ->where('id', '!=', $locked->id)
                        ->whereIn('status', ['trial', 'active'])
                        ->update(['status' => 'expired']);

                    $locked->update([
                        'status' => 'active',
                        'paid_at' => now(),
                        'notes' => 'Pembayaran Midtrans berhasil',
                    ]);
                });
            } elseif (in_array($txStatus, ['deny', 'cancel', 'expire'], true) && $sub->status === 'pending') {
                $sub->update(['status' => 'cancelled', 'notes' => 'Pembayaran Midtrans '.$txStatus]);
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
                    \App\Services\TelegramNotifier::orderPaid($order);
                }

                // Always attempt the credit, even when the cashier already confirmed
                // the order manually — creditFromQris() is idempotent, so a retried
                // webhook cannot double-credit, and a manually confirmed order does
                // not silently lose its balance.
                try {
                    $this->balanceService->creditFromQris($order);
                } catch (\Exception $e) {
                    Log::error('Balance credit failed for QRIS', ['order' => $orderNumber, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
