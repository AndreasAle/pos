<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Depreciation;
use App\Models\PlatformFinance\FixedAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aset tetap dan penyusutan garis lurus (Financial Accounting bab 11).
 * Konvensi: penyusutan mulai bulan SETELAH bulan perolehan, berhenti saat nilai buku = nilai sisa.
 */
class FixedAssets
{
    public function __construct(private Ledger $ledger) {}

    public function acquire(array $data, PlatformAdmin $by): FixedAsset
    {
        if (! $by->is_active || ! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif.');
        }
        $cost = round((float) ($data['cost'] ?? 0), 2);
        $salvage = round((float) ($data['salvage_value'] ?? 0), 2);
        $life = (int) ($data['useful_life_months'] ?? 0);
        $date = Carbon::parse($data['acquired_at'] ?? today());
        if ($cost <= 0 || $salvage < 0 || $salvage >= $cost || $life < 1 || $life > 600 || $date->isAfter(today()) || trim((string) ($data['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Isi nama, harga > 0, nilai sisa < harga, umur 1-600 bulan, tanggal tidak di masa depan.');
        }
        $paidFrom = (string) ($data['paid_from'] ?? '');
        $cash = Account::whereIn('subtype', ['cash', 'bank', 'clearing'])->pluck('code')->all();
        $credit = $paidFrom === Transactions::PAYABLE ? '2000' : (Transactions::OWNER_FUNDING[$paidFrom] ?? (in_array($paidFrom, $cash, true) ? $paidFrom : null));
        if (! $credit) {
            throw new InvalidArgumentException('Sumber pembayaran tidak valid.');
        }
        $costCenter = Ledger::costCenter($data['cost_center'] ?? null);

        return DB::transaction(function () use ($data, $cost, $salvage, $life, $date, $paidFrom, $credit, $costCenter, $by) {
            $asset = FixedAsset::create(['name' => mb_substr(trim($data['name']), 0, 150), 'acquired_at' => $date, 'cost' => $cost, 'salvage_value' => $salvage,
                'useful_life_months' => $life, 'paid_from' => $paidFrom, 'cost_center' => $costCenter, 'created_by' => $by->id]);
            $entry = $this->ledger->post($date, 'Pembelian aset: '.$asset->name, [['1500', $cost, 0, null, $costCenter], [$credit, 0, $cost]], 'manual', 'asset:'.$asset->id, null, $by->id, $data['evidence'] ?? null);
            $asset->update(['acquisition_entry_id' => $entry->id]);
            AuditEvent::log('admin', $by->id, 'asset.acquired', $asset, null, $asset->only(['name', 'cost', 'salvage_value', 'useful_life_months', 'paid_from']));

            return $asset;
        });
    }

    /** Posting penyusutan satu periode untuk semua aset aktif. Idempoten per aset per periode. */
    public function depreciate(string $period, ?int $by = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('Format periode YYYY-MM.');
        }
        $end = Carbon::parse($period.'-01')->endOfMonth()->startOfDay();
        $posted = [];
        foreach ($this->dueAssets($period) as $asset) {
            $remaining = round((float) $asset->cost - (float) $asset->salvage_value - (float) $asset->depreciations()->sum('amount'), 2);
            $amount = min($asset->monthlyDepreciation(), $remaining);
            if ($amount <= 0) {
                continue;
            }
            DB::transaction(function () use ($asset, $period, $amount, $end, $by, &$posted) {
                $entry = $this->ledger->post($end, "Penyusutan {$asset->name} {$period}", [['7300', $amount, 0, null, $asset->cost_center], ['1510', 0, $amount]], 'depreciation', "depreciation:{$asset->id}:{$period}", null, $by);
                Depreciation::firstOrCreate(['platform_fin_asset_id' => $asset->id, 'period' => $period], ['amount' => $amount, 'platform_fin_entry_id' => $entry->id]);
                $posted[] = ['asset' => $asset->name, 'amount' => $amount];
            });
        }

        return $posted;
    }

    /** Aset yang seharusnya disusutkan pada periode itu tapi belum. */
    public function dueAssets(string $period)
    {
        $start = Carbon::parse($period.'-01');

        return FixedAsset::where('status', 'active')->where('acquired_at', '<', $start->toDateString())
            ->whereDoesntHave('depreciations', fn ($q) => $q->where('period', $period))->get()
            ->filter(fn (FixedAsset $a) => round((float) $a->cost - (float) $a->salvage_value - (float) $a->depreciations()->sum('amount'), 2) > 0)->values();
    }

    public function register(): array
    {
        return FixedAsset::withSum('depreciations', 'amount')->orderBy('acquired_at')->get()->map(fn (FixedAsset $a) => [
            'id' => $a->id, 'name' => $a->name, 'acquired_at' => $a->acquired_at->toDateString(), 'cost' => (float) $a->cost,
            'accumulated' => round((float) $a->depreciations_sum_amount, 2), 'book_value' => round((float) $a->cost - (float) $a->depreciations_sum_amount, 2),
            'monthly' => $a->monthlyDepreciation(), 'life_months' => $a->useful_life_months, 'status' => $a->status,
        ])->all();
    }
}
