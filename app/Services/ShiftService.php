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
        $paidOrders = $shift->orders()->where('status', 'paid');

        $singleMethodCash = (float) (clone $paidOrders)
            ->where('is_split_payment', false)
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        $splitCash = (float) OrderPayment::whereIn(
            'order_id',
            (clone $paidOrders)->where('is_split_payment', true)->select('id')
        )->where('payment_method', 'cash')->sum('amount');

        return $singleMethodCash + $splitCash;
    }
}
