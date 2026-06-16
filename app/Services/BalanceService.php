<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\Business;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    // Platform fee percentage taken from each QRIS transaction (0 = no fee)
    private float $platformFeePercent;

    public function __construct()
    {
        $this->platformFeePercent = (float) config('app.platform_fee_percent', 0);
    }

    /**
     * Credit balance when a QRIS order is paid.
     * Called from Midtrans webhook.
     */
    public function creditFromQris(Order $order): BalanceTransaction
    {
        $business   = $order->business;
        $gross      = (float) $order->grand_total;
        $fee        = round($gross * $this->platformFeePercent / 100, 2);
        $net        = $gross - $fee;

        return DB::transaction(function () use ($business, $gross, $fee, $net, $order) {
            $balanceBefore = (float) $business->balance;
            $balanceAfter  = $balanceBefore + $net;

            $business->increment('balance', $net);
            $business->increment('total_earned', $net);

            return BalanceTransaction::create([
                'business_id'    => $business->id,
                'type'           => 'credit',
                'amount'         => $gross,
                'platform_fee'   => $fee,
                'net_amount'     => $net,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference'      => $order->order_number,
                'description'    => 'QRIS payment — Order #' . $order->order_number,
                'source'         => 'qris',
                'sourceable_type' => Order::class,
                'sourceable_id'   => $order->id,
            ]);
        });
    }

    /**
     * Debit balance when a withdrawal is completed.
     */
    public function debitForWithdrawal(WithdrawalRequest $wd): BalanceTransaction
    {
        $business = $wd->business;

        return DB::transaction(function () use ($business, $wd) {
            if ($business->balance < $wd->amount) {
                throw new \Exception('Saldo tidak mencukupi untuk proses penarikan.');
            }

            $balanceBefore = (float) $business->balance;
            $balanceAfter  = $balanceBefore - $wd->amount;

            $business->decrement('balance', $wd->amount);
            $business->increment('total_withdrawn', $wd->amount);

            return BalanceTransaction::create([
                'business_id'    => $business->id,
                'type'           => 'debit',
                'amount'         => $wd->amount,
                'platform_fee'   => 0,
                'net_amount'     => $wd->amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference'      => 'WD-' . $wd->id,
                'description'    => 'Penarikan ke ' . $wd->bank_name . ' ' . $wd->account_number,
                'source'         => 'withdrawal',
                'sourceable_type' => WithdrawalRequest::class,
                'sourceable_id'   => $wd->id,
            ]);
        });
    }

    /**
     * Reverse balance credit when a QRIS order is refunded.
     */
    public function reverseFromRefund(Order $order): BalanceTransaction
    {
        $business = $order->business;
        $gross    = (float) $order->grand_total;
        $fee      = round($gross * $this->platformFeePercent / 100, 2);
        $net      = $gross - $fee;

        return DB::transaction(function () use ($business, $gross, $fee, $net, $order) {
            $balanceBefore = (float) $business->balance;
            $balanceAfter  = max(0, $balanceBefore - $net);

            $business->decrement('balance', min($net, $balanceBefore));
            $business->decrement('total_earned', $net);

            return BalanceTransaction::create([
                'business_id'    => $business->id,
                'type'           => 'debit',
                'amount'         => $gross,
                'platform_fee'   => $fee,
                'net_amount'     => $net,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference'      => $order->order_number,
                'description'    => 'Refund Order #' . $order->order_number,
                'source'         => 'refund',
                'sourceable_type' => Order::class,
                'sourceable_id'   => $order->id,
            ]);
        });
    }
}
