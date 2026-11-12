<?php

namespace App\Services\Intelligence;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PeriodResolver
{
    public function resolve(Business $business, array $args): array
    {
        $timezone = $business->timezone ?: config('app.timezone');
        // Database timestamps are second-precision. The exclusive upper bound
        // must advance to the next second or an order written in this same
        // second would disappear from a "hari ini" query.
        $now = CarbonImmutable::now($timezone)->addSecond()->startOfSecond();
        $period = $args['period'] ?? 'today';

        [$from, $to, $label] = match ($period) {
            'yesterday' => [$now->subDay()->startOfDay(), $now->startOfDay(), 'kemarin'],
            'this_week' => [$now->startOfWeek(), $now, 'minggu ini'],
            'last_week' => [$now->subWeek()->startOfWeek(), $now->startOfWeek(), 'minggu lalu'],
            'this_month' => [$now->startOfMonth(), $now, 'bulan ini'],
            'last_month' => [$now->subMonth()->startOfMonth(), $now->startOfMonth(), 'bulan lalu'],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now, '30 hari terakhir'],
            'all_time' => [CarbonImmutable::create(2000, 1, 1, 0, 0, 0, $timezone), $now, 'sejak transaksi pertama'],
            'custom' => $this->custom($args, $now),
            default => [$now->startOfDay(), $now, 'hari ini'],
        };

        if ($from->gte($to) || ($period !== 'all_time' && $from->diffInDays($to) > 3660)) {
            throw ValidationException::withMessages(['period' => 'Rentang tanggal tidak valid atau terlalu panjang.']);
        }

        return [
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'timezone' => $timezone,
            'inclusive_start' => $from->toIso8601String(),
            'exclusive_end' => $to->toIso8601String(),
        ];
    }

    private function custom(array $args, CarbonImmutable $now): array
    {
        if (empty($args['date_from'])) {
            throw ValidationException::withMessages(['date_from' => 'Tanggal mulai wajib untuk periode custom.']);
        }

        $from = CarbonImmutable::parse($args['date_from'], $now->timezone)->startOfDay();
        $to = empty($args['date_to']) ? $now : CarbonImmutable::parse($args['date_to'], $now->timezone)->addDay()->startOfDay();
        $to = $to->min($now);

        return [$from, $to, $from->isoFormat('D MMM Y').'–'.$to->subSecond()->isoFormat('D MMM Y')];
    }
}
