<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\OrderPayment;
use App\Models\User;

class ShiftService
{
    public function openShift(User $user, int $outletId, float $openingCash): CashierShift
    {
        return CashierShift::create([
            'business_id'  => $user->business_id,
            'outlet_id'    => $outletId,
            'user_id'      => $user->id,
            'opening_cash' => $openingCash,
            'opened_at'    => now(),
            'status'       => 'open',
        ]);
    }

    public function closeShift(CashierShift $shift, float $actualCash, ?string $notes): CashierShift
    {
        $expected   = (float) $shift->opening_cash + $this->cashTakings($shift);
        $difference = $actualCash - $expected;

        $shift->update([
            'closing_cash_expected' => $expected,
            'closing_cash_actual'   => $actualCash,
            'cash_difference'       => $difference,
            'closed_at'             => now(),
            'status'                => 'closed',
            'notes'                 => $notes,
        ]);

        return $shift->fresh();
    }

    /**
     * Cash that should physically be in the drawer for this shift.
     *
     * Split orders are the reason this is not a single sum: their
     * `payment_method` only records the first method used, while `grand_total`
     * is the whole bill. Counting those as cash made the drawer look short by
     * the non-cash portion on every split sale. The real split is in
     * `order_payments`, so that is what gets summed.
     */
    public function cashTakings(CashierShift $shift): float
    {
        return $this->takingsByMethod($shift)['cash'] ?? 0.0;
    }

    /**
     * What the shift took in, per payment method: ['cash' => 152500, 'qris' => …].
     *
     * Same split-payment rule as cashTakings(): a split order contributes each
     * of its `order_payments` rows to its own method, not its whole total to
     * the first one. Cash comes first so the drawer count leads the list.
     *
     * @return array<string, float>
     */
    public function takingsByMethod(CashierShift $shift): array
    {
        $paidOrders = $shift->orders()->where('status', 'paid');

        $single = (clone $paidOrders)
            ->where('is_split_payment', false)
            ->selectRaw('payment_method, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $split = OrderPayment::whereIn(
            'order_id',
            (clone $paidOrders)->where('is_split_payment', true)->select('id')
        )
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $totals = ['cash' => 0.0];
        foreach ([$single, $split] as $rows) {
            foreach ($rows as $method => $amount) {
                $totals[$method] = ($totals[$method] ?? 0.0) + (float) $amount;
            }
        }

        return $totals;
    }
}
