<?php

namespace App\Services;

use App\Models\CashierShift;
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
        $cashSales = (float) $shift->orders()
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        $expected   = (float) $shift->opening_cash + $cashSales;
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
}
