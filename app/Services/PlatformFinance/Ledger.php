<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Line;
use App\Models\PlatformFinance\Period;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buku besar double-entry untuk keuangan Yoka sendiri.
 *
 * Aturan (Principles of Accounting Vol. 1, bab 3-5):
 *  - persamaan akuntansi Aset = Kewajiban + Ekuitas dijaga oleh jurnal yang selalu seimbang (debit = kredit),
 *  - jurnal yang sudah diposting tidak diubah/dihapus; koreksi dengan jurnal pembalik (audit trail),
 *  - periode yang sudah ditutup terkunci,
 *  - idempotency key mencegah pencatatan ganda (misalnya webhook Midtrans terkirim dua kali).
 * Semua angka dihitung dalam sen (integer) supaya tidak ada galat pembulatan.
 */
class Ledger
{
    /**
     * @param  array<int, array{0:string, 1:float|int|string, 2:float|int|string, 3?:?string, 4?:?string}>  $lines  [kode akun, debit, kredit, memo, cost center]
     */
    public function post(string|CarbonInterface $date, string $description, array $lines, string $source = 'manual', ?string $idempotencyKey = null, ?string $reference = null, ?int $createdBy = null, ?string $evidence = null): Entry
    {
        $date = Carbon::parse($date)->startOfDay();
        if ($idempotencyKey && ($existing = Entry::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Jurnal minimal dua baris (double-entry).');
        }
        $this->assertOpen($date);

        $accounts = Account::whereIn('code', array_column($lines, 0))->get()->keyBy('code');
        $debit = $credit = 0;
        $rows = [];
        foreach ($lines as $l) {
            $account = $accounts[$l[0]] ?? throw new InvalidArgumentException("Akun {$l[0]} tidak ada.");
            if (! $account->is_active) {
                throw new InvalidArgumentException("Akun {$l[0]} nonaktif.");
            }
            $d = self::cents($l[1]);
            $c = self::cents($l[2]);
            if ($d < 0 || $c < 0 || ($d > 0) === ($c > 0)) {
                throw new InvalidArgumentException('Setiap baris harus berisi debit ATAU kredit, bernilai positif.');
            }
            $debit += $d;
            $credit += $c;
            $rows[] = ['platform_fin_account_id' => $account->id, 'debit' => $d / 100.0, 'credit' => $c / 100.0, 'memo' => $l[3] ?? null, 'cost_center' => self::costCenter($l[4] ?? null)];
        }
        if ($debit !== $credit) {
            throw new InvalidArgumentException('Jurnal tidak seimbang: debit '.number_format($debit / 100.0, 2).' vs kredit '.number_format($credit / 100.0, 2).'.');
        }

        return DB::transaction(function () use ($date, $description, $source, $idempotencyKey, $reference, $createdBy, $evidence, $rows) {
            $entry = Entry::create([
                'entry_date' => $date, 'description' => mb_substr($description, 0, 255), 'source' => $source,
                'idempotency_key' => $idempotencyKey, 'reference' => $reference, 'created_by' => $createdBy, 'evidence' => $evidence,
            ]);
            foreach ($rows as $row) {
                Line::create($row + ['platform_fin_entry_id' => $entry->id]);
            }

            return $entry->load('lines.account');
        });
    }

    public const COST_CENTERS = ['platform' => 'Platform & server', 'ai' => 'AI', 'cs' => 'Customer service', 'marketing' => 'Marketing', 'umum' => 'Umum'];

    public static function costCenter(?string $c): ?string
    {
        if ($c === null || $c === '') {
            return null;
        }
        if (! array_key_exists($c, self::COST_CENTERS)) {
            throw new InvalidArgumentException("Cost center {$c} tidak dikenal.");
        }

        return $c;
    }

    /** Beban per cost center untuk periode (baris tanpa cost center masuk "tanpa"). */
    public function costCenterReport(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Line::query()->join('platform_fin_entries as e', 'e.id', '=', 'platform_fin_lines.platform_fin_entry_id')
            ->join('platform_fin_accounts as a', 'a.id', '=', 'platform_fin_lines.platform_fin_account_id')
            ->where('a.type', 'expense')->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('platform_fin_lines.cost_center')
            ->selectRaw('platform_fin_lines.cost_center as cc, SUM(platform_fin_lines.debit) - SUM(platform_fin_lines.credit) as total')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->cc ?? 'tanpa'] = round((float) $r->total, 2);
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'labels' => self::COST_CENTERS + ['tanpa' => 'Belum diberi cost center'], 'totals' => $out];
    }

    /** Koreksi yang bisa diaudit: jurnal cermin (debit <-> kredit), jurnal asli tetap ada. */
    public function reverse(Entry $entry, string $reason, ?int $by = null, string|CarbonInterface|null $date = null): Entry
    {
        if ($entry->reversed_by_id || $entry->reversal_of_id) {
            throw new InvalidArgumentException('Jurnal ini sudah dibalik atau merupakan jurnal pembalik.');
        }
        $date = $date ? Carbon::parse($date) : ($this->isClosed($entry->entry_date) ? today() : $entry->entry_date);
        $lines = $entry->lines()->with('account')->get()
            ->map(fn (Line $l) => [$l->account->code, $l->credit, $l->debit, 'Pembalik: '.$l->memo, $l->cost_center])->all();

        return DB::transaction(function () use ($entry, $reason, $by, $date, $lines) {
            $reversal = $this->post($date, 'Pembalik #'.$entry->id.': '.$reason, $lines, 'reversal', 'reversal:'.$entry->id, $entry->reference, $by);
            Entry::whereKey($reversal->id)->update(['reversal_of_id' => $entry->id]);
            $entry->update(['reversed_by_id' => $reversal->id]);

            return $reversal->fresh();
        });
    }

    public function closePeriod(string $period, ?int $by = null): Period
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('Format periode YYYY-MM.');
        }

        $row = Period::firstOrNew(['period' => $period]);
        if ($row->exists && $row->reopened_at === null) {
            return $row;
        }
        $row->fill(['closed_at' => now(), 'closed_by' => $by, 'reopened_at' => null])->save();

        return $row;
    }

    public function isClosed(CarbonInterface $date): bool
    {
        return Period::where('period', $date->format('Y-m'))->whereNull('reopened_at')->exists();
    }

    private function assertOpen(CarbonInterface $date): void
    {
        if ($this->isClosed($date)) {
            throw new InvalidArgumentException('Periode '.$date->format('Y-m').' sudah ditutup. Catat koreksi di periode berjalan.');
        }
    }

    /**
     * Saldo per akun (dalam rupiah, tanda mengikuti saldo normal akun).
     *
     * @return array<string, array{code:string,name:string,type:string,subtype:?string,is_cash:bool,report_group:?string,behavior:?string,debit:float,credit:float,balance:float}>
     */
    public function balances(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $sums = Line::query()
            ->join('platform_fin_entries as e', 'e.id', '=', 'platform_fin_lines.platform_fin_entry_id')
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to->toDateString()))
            ->groupBy('platform_fin_account_id')
            ->selectRaw('platform_fin_account_id, SUM(debit) as d, SUM(credit) as c')
            ->get()->keyBy('platform_fin_account_id');

        $out = [];
        foreach (Account::orderBy('code')->get() as $a) {
            $d = self::cents($sums[$a->id]->d ?? 0);
            $c = self::cents($sums[$a->id]->c ?? 0);
            $out[$a->code] = [
                'code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'subtype' => $a->subtype, 'is_cash' => $a->is_cash,
                'report_group' => $a->report_group, 'behavior' => $a->behavior,
                'debit' => $d / 100.0, 'credit' => $c / 100.0, 'balance' => ($a->isDebitNormal() ? $d - $c : $c - $d) / 100.0,
            ];
        }

        return $out;
    }

    /** Neraca saldo: total debit harus sama dengan total kredit. */
    public function trialBalance(?CarbonInterface $asOf = null): array
    {
        $rows = [];
        $td = $tc = 0;
        foreach ($this->balances(null, $asOf) as $b) {
            $net = self::cents($b['debit']) - self::cents($b['credit']);
            if ($net === 0) {
                continue;
            }
            $rows[] = ['code' => $b['code'], 'name' => $b['name'], 'debit' => max($net, 0) / 100.0, 'credit' => max(-$net, 0) / 100.0];
            $td += max($net, 0);
            $tc += max(-$net, 0);
        }

        return ['as_of' => ($asOf ?? today())->toDateString(), 'rows' => $rows, 'total_debit' => $td / 100.0, 'total_credit' => $tc / 100.0, 'balanced' => $td === $tc];
    }

    /** Laporan laba rugi (multi-step): pendapatan bersih, laba kotor, kontribusi setelah marketing, laba bersih. */
    public function incomeStatement(CarbonInterface $from, CarbonInterface $to): array
    {
        $b = $this->balances($from, $to);
        $sum = fn (callable $f) => array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($b, $f))) / 100.0;

        $gross = $sum(fn ($x) => $x['type'] === 'revenue' && $x['subtype'] !== 'contra_revenue');
        $refunds = $sum(fn ($x) => $x['subtype'] === 'contra_revenue');
        $revenue = round($gross - $refunds, 2);
        $cor = $sum(fn ($x) => $x['type'] === 'expense' && $x['report_group'] === 'cost_of_revenue');
        $marketing = $sum(fn ($x) => $x['type'] === 'expense' && $x['report_group'] === 'marketing');
        $operating = $sum(fn ($x) => $x['type'] === 'expense' && in_array($x['report_group'], ['operating', 'other', null], true));
        $variable = $sum(fn ($x) => $x['type'] === 'expense' && $x['behavior'] === 'variable');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'gross_revenue' => $gross, 'refunds' => $refunds, 'net_revenue' => $revenue,
            'cost_of_revenue' => $cor, 'gross_profit' => round($revenue - $cor, 2),
            'marketing' => $marketing, 'operating_expenses' => $operating,
            'total_expenses' => round($cor + $marketing + $operating, 2),
            'net_income' => round($revenue - $cor - $marketing - $operating, 2),
            'variable_costs' => $variable, 'contribution_margin' => round($revenue - $variable, 2),
            'expense_lines' => array_values(array_map(fn ($x) => ['code' => $x['code'], 'name' => $x['name'], 'amount' => $x['balance']],
                array_filter($b, fn ($x) => $x['type'] === 'expense' && $x['balance'] != 0))),
        ];
    }

    /** Neraca: aset = kewajiban + ekuitas (termasuk laba berjalan yang belum ditutup). */
    public function balanceSheet(?CarbonInterface $asOf = null): array
    {
        $b = $this->balances(null, $asOf);
        $group = fn (string $type) => array_values(array_filter($b, fn ($x) => $x['type'] === $type && $x['balance'] != 0));
        $total = fn (array $rows) => array_sum(array_map(fn ($x) => self::cents($x['balance']), $rows));

        $assets = $group('asset');
        $contra = array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($b, fn ($x) => $x['subtype'] === 'contra_asset')));
        $liabilities = $group('liability');
        $equity = array_values(array_filter($b, fn ($x) => $x['type'] === 'equity' && $x['subtype'] !== 'drawing' && $x['balance'] != 0));
        $drawings = array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($b, fn ($x) => $x['subtype'] === 'drawing')));
        $income = array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($b, fn ($x) => $x['type'] === 'revenue' && $x['subtype'] !== 'contra_revenue')))
            - array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($b, fn ($x) => $x['subtype'] === 'contra_revenue' || $x['type'] === 'expense')));

        $ta = $total(array_filter($assets, fn ($x) => $x['subtype'] !== 'contra_asset')) - $contra;
        $tl = $total($liabilities);
        $te = $total($equity) - $drawings + $income;

        return [
            'as_of' => ($asOf ?? today())->toDateString(),
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
            'drawings' => $drawings / 100.0, 'current_earnings' => $income / 100.0,
            'total_assets' => $ta / 100.0, 'total_liabilities' => $tl / 100.0, 'total_equity' => $te / 100.0,
            'balanced' => $ta === $tl + $te,
        ];
    }

    /**
     * Laporan arus kas metode langsung (Vol. 1 bab 16.6): mutasi akun kas/bank dikelompokkan menurut
     * akun lawannya. Pemilik/pinjaman = pendanaan; aset jangka panjang = investasi; selain itu operasi.
     * Pindah dana antar akun kas tidak dihitung sebagai arus masuk/keluar.
     */
    public function cashFlow(CarbonInterface $from, CarbonInterface $to): array
    {
        $cashIds = Account::where('is_cash', true)->pluck('id')->all();
        $entries = Entry::with('lines.account')->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->get();
        $flow = ['operating' => 0, 'investing' => 0, 'financing' => 0];
        foreach ($entries as $entry) {
            $cashNet = 0;
            $others = [];
            foreach ($entry->lines as $l) {
                if (in_array($l->platform_fin_account_id, $cashIds, true)) {
                    $cashNet += self::cents($l->debit) - self::cents($l->credit);
                } else {
                    $others[] = $l->account;
                }
            }
            if ($cashNet === 0) {
                continue;
            }
            $section = 'operating';
            foreach ($others as $a) {
                if ($a->type === 'equity' || in_array($a->subtype, ['owner_loan'], true)) {
                    $section = 'financing';
                } elseif ($a->type === 'asset' && $a->subtype === 'fixed') {
                    $section = 'investing';
                }
            }
            $flow[$section] += $cashNet;
        }
        $opening = $this->cashBalance($from->copy()->subDay());

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'operating' => $flow['operating'] / 100.0, 'investing' => $flow['investing'] / 100.0, 'financing' => $flow['financing'] / 100.0,
            'net_change' => array_sum($flow) / 100.0, 'opening_cash' => $opening, 'closing_cash' => round($opening + array_sum($flow) / 100.0, 2),
        ];
    }

    /** Kas + bank (tidak termasuk saldo Midtrans yang belum cair). */
    public function cashBalance(?CarbonInterface $asOf = null): float
    {
        return array_sum(array_map(fn ($x) => self::cents($x['balance']), array_filter($this->balances(null, $asOf), fn ($x) => $x['is_cash']))) / 100.0;
    }

    public static function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
