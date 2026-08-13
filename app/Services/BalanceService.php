<?php

namespace App\Services;

use App\Exceptions\BalanceException;
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
     *
     * Called from both the Midtrans webhook and the cashier's manual confirmation,
     * and Midtrans retries webhooks — so this must be safe to call more than once
     * for the same order. Returns the existing row instead of crediting twice.
     */
    public function creditFromQris(Order $order): BalanceTransaction
    {
        return DB::transaction(function () use ($order) {
            $existing = $this->findTransaction($order, 'credit');

            if ($existing) {
                return $existing;
            }

            $business = $this->lockBusiness($order->business_id);
            $gross    = (float) $order->grand_total;
            $fee      = round($gross * $this->platformFeePercent / 100, 2);
            $net      = $gross - $fee;

            return $this->apply($business, $order, [
                'type'        => 'credit',
                'delta'       => $net,
                'amount'      => $gross,
                'fee'         => $fee,
                'net'         => $net,
                'source'      => 'qris',
                'reference'   => $order->order_number,
                'description' => 'QRIS payment — Order #' . $order->order_number,
                'earned'      => $net,
            ]);
        });
    }

    /**
     * Reverse a QRIS credit when the order is refunded or voided.
     *
     * No-op when the order was never credited (cash orders, or a QRIS order whose
     * payment never settled) — money that never arrived must never be clawed back.
     */
    public function reverseFromRefund(Order $order): ?BalanceTransaction
    {
        return DB::transaction(function () use ($order) {
            $credit = $this->findTransaction($order, 'credit');

            if (!$credit) {
                return null;
            }

            if ($this->findTransaction($order, 'debit')) {
                return null; // already reversed
            }

            $business = $this->lockBusiness($order->business_id);
            $net      = (float) $credit->net_amount;

            return $this->apply($business, $order, [
                'type'        => 'debit',
                'delta'       => -$net,
                'amount'      => (float) $credit->amount,
                'fee'         => (float) $credit->platform_fee,
                'net'         => $net,
                'source'      => 'refund',
                'reference'   => $order->order_number,
                'description' => 'Refund Order #' . $order->order_number,
                'earned'      => -$net,
            ]);
        });
    }

    /**
     * Debit balance when a withdrawal is completed.
     *
     * @throws BalanceException when the balance cannot cover the request.
     */
    public function debitForWithdrawal(WithdrawalRequest $wd): BalanceTransaction
    {
        return DB::transaction(function () use ($wd) {
            $existing = $this->findTransaction($wd, 'debit');

            if ($existing) {
                throw new BalanceException('Penarikan ini sudah pernah diproses.');
            }

            // Lock first, then read: two admins clicking "selesai" at the same
            // moment must not both pass the balance check.
            $business = $this->lockBusiness($wd->business_id);
            $amount   = (float) $wd->amount;

            if ((float) $business->balance < $amount) {
                throw new BalanceException(sprintf(
                    'Saldo tidak mencukupi untuk proses penarikan. Saldo Rp %s, diminta Rp %s.',
                    number_format((float) $business->balance, 0, ',', '.'),
                    number_format($amount, 0, ',', '.')
                ));
            }

            return $this->apply($business, $wd, [
                'type'        => 'debit',
                'delta'       => -$amount,
                'amount'      => $amount,
                'fee'         => 0,
                'net'         => $amount,
                'source'      => 'withdrawal',
                'reference'   => 'WD-' . $wd->id,
                'description' => 'Penarikan ke ' . $wd->bank_name . ' ' . $wd->account_number,
                'withdrawn'   => $amount,
            ]);
        });
    }

    /**
     * Re-read the business inside the transaction with a row lock held, so the
     * balance we check is the balance we write against.
     */
    private function lockBusiness(int $businessId): Business
    {
        return Business::whereKey($businessId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Has this source already produced a transaction of the given type?
     */
    private function findTransaction(Order|WithdrawalRequest $source, string $type): ?BalanceTransaction
    {
        return BalanceTransaction::where('sourceable_type', $source::class)
            ->where('sourceable_id', $source->id)
            ->where('type', $type)
            ->first();
    }

    /**
     * Move the balance and write the matching ledger row.
     *
     * @param  array<string, mixed>  $spec
     */
    private function apply(Business $business, Order|WithdrawalRequest $source, array $spec): BalanceTransaction
    {
        $before = (float) $business->balance;
        $after  = $before + $spec['delta'];

        $business->balance = $after;

        if (isset($spec['earned'])) {
            $business->total_earned = (float) $business->total_earned + $spec['earned'];
        }

        if (isset($spec['withdrawn'])) {
            $business->total_withdrawn = (float) $business->total_withdrawn + $spec['withdrawn'];
        }

        $business->save();

        return BalanceTransaction::create([
            'business_id'     => $business->id,
            'type'            => $spec['type'],
            'amount'          => $spec['amount'],
            'platform_fee'    => $spec['fee'],
            'net_amount'      => $spec['net'],
            'balance_before'  => $before,
            'balance_after'   => $after,
            'reference'       => $spec['reference'],
            'description'     => $spec['description'],
            'source'          => $spec['source'],
            'sourceable_type' => $source::class,
            'sourceable_id'   => $source->id,
        ]);
    }
}
