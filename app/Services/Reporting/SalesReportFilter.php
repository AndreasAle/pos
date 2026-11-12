<?php

namespace App\Services\Reporting;

use App\Models\ProductCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Validated, tenant-scoped filter shared by every sales-report surface (UI,
 * exports, drill-down, AI hand-off). business_id and the outlet whitelist come
 * from the authenticated user, never from the request: query parameters can
 * only narrow what the user is already allowed to see.
 */
class SalesReportFilter
{
    public const PRESETS = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'];

    public const GROUPINGS = ['day', 'weekday', 'hour', 'week', 'month'];

    public const COMPARISONS = ['none', 'previous', 'custom', 'outlets'];

    public const PAYMENT_METHODS = ['cash', 'qris', 'transfer', 'ewallet', 'debit', 'other'];

    public const MAX_DAYS = 400;

    public function __construct(
        public readonly int $businessId,
        public readonly string $timezone,
        public readonly CarbonImmutable $start,      // inclusive, business-local
        public readonly CarbonImmutable $end,        // exclusive, business-local
        public readonly array $outletIds,            // authorized + requested
        public readonly array $allowedOutletIds,     // everything this user may see
        public readonly string $preset,
        public readonly string $grouping,
        public readonly string $comparison,
        public readonly ?CarbonImmutable $compareStart,
        public readonly ?CarbonImmutable $compareEnd,
        public readonly array $compareOutletIds,
        public readonly array $cashierIds,
        public readonly array $categoryIds,
        public readonly array $paymentMethods,
        public readonly ?CarbonImmutable $asOf,
    ) {}

    public static function fromRequest(User $user, array $input, ?CarbonImmutable $now = null): self
    {
        $business = $user->business;
        $tz = $business->timezone ?: config('app.timezone');
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);

        $allowed = self::allowedOutlets($user);
        if (! $allowed) {
            throw ValidationException::withMessages(['outlet_ids' => 'Tidak ada outlet aktif dalam akses kamu.']);
        }

        $outletIds = self::ids($input['outlet_ids'] ?? ($input['outlet_id'] ?? []));
        if ($bad = array_diff($outletIds, $allowed)) {
            throw ValidationException::withMessages(['outlet_ids' => 'Outlet tidak tersedia dalam akses kamu.']);
        }

        $preset = (string) ($input['preset'] ?? (isset($input['date_from']) ? 'custom' : 'this_month'));
        if (! in_array($preset, self::PRESETS, true)) {
            throw ValidationException::withMessages(['preset' => 'Periode tidak dikenal.']);
        }

        [$start, $end] = self::window($preset, $input, $now);
        if ($start->gte($end)) {
            throw ValidationException::withMessages(['date_from' => 'Tanggal akhir harus setelah tanggal mulai.']);
        }
        if ($start->diffInDays($end) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['date_to' => 'Rentang maksimal '.self::MAX_DAYS.' hari.']);
        }

        $grouping = (string) ($input['grouping'] ?? 'day');
        if (! in_array($grouping, self::GROUPINGS, true)) {
            throw ValidationException::withMessages(['grouping' => 'Pengelompokan tidak dikenal.']);
        }

        $comparison = (string) ($input['comparison'] ?? 'none');
        if (! in_array($comparison, self::COMPARISONS, true)) {
            throw ValidationException::withMessages(['comparison' => 'Mode perbandingan tidak dikenal.']);
        }

        $cStart = $cEnd = null;
        $cOutlets = [];
        if ($comparison === 'previous') {
            $days = max(1, (int) $start->diffInDays($end));
            $cStart = $start->subDays($days);
            $cEnd = $start;
        } elseif ($comparison === 'custom') {
            if (empty($input['compare_from']) || empty($input['compare_to'])) {
                throw ValidationException::withMessages(['compare_from' => 'Isi tanggal pembanding.']);
            }
            $cStart = self::day($input['compare_from'], $tz, 'compare_from');
            $cEnd = self::day($input['compare_to'], $tz, 'compare_to')->addDay();
            if ($cStart->gte($cEnd) || $cStart->diffInDays($cEnd) > self::MAX_DAYS) {
                throw ValidationException::withMessages(['compare_from' => 'Rentang pembanding tidak valid.']);
            }
        } elseif ($comparison === 'outlets') {
            $cOutlets = self::ids($input['compare_outlet_ids'] ?? []);
            if (! $cOutlets || array_diff($cOutlets, $allowed)) {
                throw ValidationException::withMessages(['compare_outlet_ids' => 'Pilih outlet pembanding yang tersedia dalam akses kamu.']);
            }
        }

        $methods = array_values(array_unique(array_map('strval', (array) ($input['payment_methods'] ?? ($input['payment_method'] ?? [])))));
        $methods = array_values(array_filter($methods, fn ($m) => $m !== ''));
        if (array_diff($methods, self::PAYMENT_METHODS)) {
            throw ValidationException::withMessages(['payment_methods' => 'Metode pembayaran tidak dikenal.']);
        }

        $cashiers = self::ids($input['cashier_ids'] ?? ($input['cashier_id'] ?? []));
        if ($cashiers) {
            $valid = $business->users()->whereIn('id', $cashiers)->pluck('id')->map(fn ($i) => (int) $i)->all();
            if (array_diff($cashiers, $valid)) {
                throw ValidationException::withMessages(['cashier_ids' => 'Kasir tidak ditemukan.']);
            }
        }

        $categories = self::ids($input['category_ids'] ?? ($input['category_id'] ?? []));
        if ($categories) {
            $valid = ProductCategory::where('business_id', $business->id)->whereIn('id', $categories)->pluck('id')->map(fn ($i) => (int) $i)->all();
            if (array_diff($categories, $valid)) {
                throw ValidationException::withMessages(['category_ids' => 'Kategori tidak ditemukan.']);
            }
        }

        return new self(
            (int) $business->id, $tz, $start, $end, $outletIds ?: $allowed, $allowed, $preset, $grouping, $comparison,
            $cStart, $cEnd, $cOutlets, $cashiers, $categories, $methods, $now
        );
    }

    /**
     * Filter for an already-resolved window (Tanya Yoka's period resolver). Outlets are
     * intersected with what the user may see, so a tool call can only narrow scope.
     *
     * @param  array<int,int>  $outletIds
     */
    public static function forRange(User $user, \DateTimeInterface $from, \DateTimeInterface $to, array $outletIds): self
    {
        $tz = $user->business->timezone ?: config('app.timezone');
        $allowed = self::allowedOutlets($user);
        $outletIds = array_values(array_intersect(array_map('intval', $outletIds), $allowed));
        if (! $allowed || ! $outletIds) {
            throw ValidationException::withMessages(['outlet_ids' => 'Outlet tidak tersedia dalam akses kamu.']);
        }
        $start = CarbonImmutable::instance($from)->setTimezone($tz);
        $end = CarbonImmutable::instance($to)->setTimezone($tz);

        return new self((int) $user->business_id, $tz, $start, $end, $outletIds, $allowed, 'custom', 'day', 'none', null, null, [], [], [], [], CarbonImmutable::now($tz));
    }

    /** Outlets this user may report on: all active ones for an owner/unbound admin, only their own when bound. */
    public static function allowedOutlets(User $user): array
    {
        $q = $user->business->outlets()->where('is_active', true);
        if ($user->role !== 'owner' && $user->outlet_id) {
            $q->where('id', $user->outlet_id);
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} local [start, end) */
    private static function window(string $preset, array $input, CarbonImmutable $now): array
    {
        $tz = $now->timezone->getName();
        // The window for "today"-style presets stops at the next second so an order
        // written in this very second is still inside it (timestamps are second-precision).
        $until = $now->addSecond()->startOfSecond();

        return match ($preset) {
            'today' => [$now->startOfDay(), $now->startOfDay()->addDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->startOfDay()],
            'this_week' => [$now->startOfWeek(), $now->startOfWeek()->addWeek()],
            'last_week' => [$now->subWeek()->startOfWeek(), $now->startOfWeek()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth()],
            'custom' => [
                self::day($input['date_from'] ?? $now->startOfMonth()->toDateString(), $tz, 'date_from'),
                self::day($input['date_to'] ?? $now->toDateString(), $tz, 'date_to')->addDay(),
            ],
            default => [$now->startOfMonth(), $now->startOfMonth()->addMonthNoOverflow()],
        };
    }

    private static function day(mixed $value, string $tz, string $field): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Format tanggal harus YYYY-MM-DD.']);
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Tanggal tidak valid.']);
        }
    }

    private static function ids(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            if (! is_numeric($v) || (int) $v <= 0) {
                throw ValidationException::withMessages(['filter' => 'Filter tidak valid.']);
            }
            $out[] = (int) $v;
        }

        return array_values(array_unique($out));
    }

    /** Last *inclusive* local day of the window (for display / URL state). */
    public function dateFrom(): string
    {
        return $this->start->toDateString();
    }

    public function dateTo(): string
    {
        return $this->end->subSecond()->toDateString();
    }

    public function isPartialToday(): bool
    {
        return $this->asOf !== null && $this->asOf->lt($this->end);
    }

    /** True when the compare window ends in the future relative to now (partial vs full period). */
    public function comparisonIsPartial(): bool
    {
        return $this->comparison !== 'none' && $this->isPartialToday();
    }

    /** Stable hash of every parameter that changes the numbers (used for audit + cache keys). */
    public function hash(): string
    {
        return substr(sha1(json_encode($this->toQuery())), 0, 16);
    }

    /** Canonical URL query that reproduces this filter exactly (the "URL state"). */
    public function toQuery(): array
    {
        return array_filter([
            'preset' => $this->preset,
            'date_from' => $this->dateFrom(),
            'date_to' => $this->dateTo(),
            'outlet_ids' => $this->outletIds === $this->allowedOutletIds ? null : $this->outletIds,
            'cashier_ids' => $this->cashierIds ?: null,
            'category_ids' => $this->categoryIds ?: null,
            'payment_methods' => $this->paymentMethods ?: null,
            'grouping' => $this->grouping,
            'comparison' => $this->comparison === 'none' ? null : $this->comparison,
            'compare_from' => $this->comparison === 'custom' ? $this->compareStart?->toDateString() : null,
            'compare_to' => $this->comparison === 'custom' ? $this->compareEnd?->subSecond()->toDateString() : null,
            'compare_outlet_ids' => $this->comparison === 'outlets' ? $this->compareOutletIds : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** Same filter pointed at another window/outlet set — used for the comparison side. */
    public function withWindow(CarbonImmutable $start, CarbonImmutable $end, ?array $outletIds = null): self
    {
        return new self(
            $this->businessId, $this->timezone, $start, $end, $outletIds ?? $this->outletIds, $this->allowedOutletIds,
            'custom', $this->grouping, 'none', null, null, [], $this->cashierIds, $this->categoryIds, $this->paymentMethods, $this->asOf
        );
    }

    public function withGrouping(string $grouping): self
    {
        return new self(
            $this->businessId, $this->timezone, $this->start, $this->end, $this->outletIds, $this->allowedOutletIds, $this->preset,
            $grouping, $this->comparison, $this->compareStart, $this->compareEnd, $this->compareOutletIds, $this->cashierIds,
            $this->categoryIds, $this->paymentMethods, $this->asOf
        );
    }

    public function comparisonFilter(): ?self
    {
        return match ($this->comparison) {
            'previous', 'custom' => $this->withWindow($this->compareStart, $this->compareEnd),
            'outlets' => $this->withWindow($this->start, $this->end, $this->compareOutletIds),
            default => null,
        };
    }

    /** Local-day bounds converted to the app/DB timezone, which is how created_at is stored. */
    public function dbRange(): array
    {
        $db = config('app.timezone');

        return [$this->start->setTimezone($db), $this->end->setTimezone($db)];
    }

    /** Seconds to add to a stored (app-timezone) timestamp to read it as business-local time. */
    public function dbOffsetSeconds(): int
    {
        $db = config('app.timezone');
        $at = $this->start->setTimezone($db);

        return (int) ($this->start->utcOffset() - $at->utcOffset()) * 60;
    }
}
