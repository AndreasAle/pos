<?php

namespace App\Support;

class Money
{
    /** Rupiah, no decimals; null renders as the honest "N/A" instead of Rp 0. */
    public static function rp(float|int|null $v): string
    {
        return $v === null ? 'N/A' : ($v < 0 ? '-' : '').'Rp '.number_format(abs($v), 0, ',', '.');
    }

    public static function num(float|int|null $v, int $dec = 0): string
    {
        return $v === null ? 'N/A' : number_format($v, $dec, ',', '.');
    }

    /** "+12,5%" / "N/A" for a delta produced by SalesMetricsService::delta(). */
    public static function pct(?float $v): string
    {
        return $v === null ? 'N/A' : ($v > 0 ? '+' : '').number_format($v, 1, ',', '.').'%';
    }

    public static function method(string $m): string
    {
        return ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya'][$m] ?? ucfirst($m);
    }
}
