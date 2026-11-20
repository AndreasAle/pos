<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Biaya OpenAI PERSIS dari Costs API organisasi (angka yang sama dengan dashboard billing OpenAI),
 * memakai Admin key baca (OPENAI_ADMIN_KEY di .env server, tidak pernah di repo).
 *
 * Saldo kredit tidak tersedia lewat API resmi, jadi dihitung:
 *   saldo = saldo awal + kredit dibeli + kredit gratis - biaya sejak tanggal saldo awal.
 * Angka biaya persis; saldo sama dengan dashboard selama setiap top-up/kredit dicatat di sini.
 *
 * Pembukuan: beli kredit = Biaya Dibayar di Muka (Dr 1400 / Cr bank); pemakaian bulanan = draf beban
 * AI (Dr 5100 / Cr 1400) yang tetap harus disetujui manusia. Tidak ada yang diposting diam-diam.
 */
class OpenAiBilling
{
    public const PROVIDER = 'openai';

    public function __construct(private Ledger $ledger) {}

    public function configured(): bool
    {
        return filled(config('services.openai_admin.key'));
    }

    /** Tarik biaya harian (dikelompokkan per line item & project). Idempoten: baris yang sama diperbarui, tidak dobel. */
    public function sync(int $days = 31): int
    {
        if (! $this->configured()) {
            throw new RuntimeException('OPENAI_ADMIN_KEY belum diisi di .env server.');
        }
        $days = max(1, min(180, $days));
        $start = today()->subDays($days - 1)->startOfDay();
        $query = ['start_time' => $start->timestamp, 'bucket_width' => '1d', 'limit' => min(180, $days), 'group_by' => ['line_item', 'project_id']];
        $rows = 0;
        $page = null;
        $now = now();

        for ($guard = 0; $guard < 20; $guard++) {
            $res = Http::withToken((string) config('services.openai_admin.key'))->acceptJson()->timeout(20)
                ->get(rtrim((string) config('services.openai_admin.url'), '/').'/organization/costs', $query + ($page ? ['page' => $page] : []));
            if ($res->status() === 401 || $res->status() === 403) {
                throw new RuntimeException('Admin key ditolak OpenAI (401/403). Pastikan memakai Admin key organisasi dengan izin baca usage.');
            }
            if (! $res->successful()) {
                throw new RuntimeException('Costs API OpenAI gagal: HTTP '.$res->status().'.');
            }
            DB::transaction(function () use ($res, $now, &$rows) {
                foreach ((array) $res->json('data', []) as $bucket) {
                    $date = Carbon::createFromTimestampUTC((int) ($bucket['start_time'] ?? 0))->toDateString();
                    foreach ((array) ($bucket['results'] ?? []) as $r) {
                        $usd = (float) data_get($r, 'amount.value', 0);
                        if (strtolower((string) data_get($r, 'amount.currency', 'usd')) !== 'usd') {
                            continue;
                        }
                        DB::table('platform_fin_ai_usage')->updateOrInsert(
                            ['provider' => self::PROVIDER, 'usage_date' => $date, 'line_item' => mb_substr((string) ($r['line_item'] ?? ''), 0, 150), 'project_id' => mb_substr((string) ($r['project_id'] ?? ''), 0, 80)],
                            ['amount_usd' => round($usd, 6), 'synced_at' => $now, 'updated_at' => $now, 'created_at' => $now]
                        );
                        $rows++;
                    }
                }
            });
            if (! $res->json('has_more') || ! ($page = $res->json('next_page'))) {
                break;
            }
        }
        Cache::forever('finance:openai:last_sync', $now->toIso8601String());

        return $rows;
    }

    /** Catat saldo awal / pembelian / kredit gratis. Pembelian dan saldo awal dibukukan ke Biaya Dibayar di Muka. */
    public function recordCredit(array $data, PlatformAdmin $by): object
    {
        if (! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif.');
        }
        $kind = (string) ($data['kind'] ?? '');
        $usd = round((float) ($data['amount_usd'] ?? 0), 2);
        $idr = round((float) ($data['amount_idr'] ?? 0), 2);
        $date = Carbon::parse($data['credited_at'] ?? today());
        if (! in_array($kind, ['opening', 'purchase', 'grant'], true) || $usd <= 0 || $date->isAfter(today())) {
            throw new InvalidArgumentException('Pilih jenis, isi jumlah USD > 0, tanggal tidak di masa depan.');
        }
        if ($kind === 'opening' && DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->where('kind', 'opening')->exists()) {
            throw new InvalidArgumentException('Saldo awal sudah dicatat. Koreksi lewat jurnal pembalik.');
        }
        if (in_array($kind, ['opening', 'purchase'], true) && $idr <= 0) {
            throw new InvalidArgumentException('Isi nilai rupiahnya (yang terpotong di kartu/bank, atau kurs hari itu untuk saldo awal).');
        }
        $credit = match ($kind) {
            'purchase' => $this->creditAccount((string) ($data['paid_from'] ?? '')),
            'opening' => '3000', // kredit yang sudah dibayar pemilik sebelum pembukuan dimulai
            default => null,
        };

        $baseline = 0.0;
        if ($kind === 'opening') {
            if ($this->configured()) {
                try {
                    $this->sync(3);
                } catch (RuntimeException) {
                    // tetap catat; kalibrasi bisa dilakukan nanti
                }
            }
            $baseline = (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->where('usage_date', $date->toDateString())->sum('amount_usd');
        }

        return DB::transaction(function () use ($data, $kind, $usd, $idr, $date, $credit, $by, $baseline) {
            $id = DB::table('platform_fin_ai_credits')->insertGetId([
                'usage_baseline_usd' => round($baseline, 6),
                'provider' => self::PROVIDER, 'kind' => $kind, 'credited_at' => $date->toDateString(), 'amount_usd' => $usd, 'amount_idr' => $kind === 'grant' ? 0 : $idr,
                'paid_from' => $data['paid_from'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'reference' => isset($data['reference']) ? mb_substr((string) $data['reference'], 0, 120) : null,
                'created_by' => $by->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($credit) {
                $entry = $this->ledger->post($date, ($kind === 'opening' ? 'Saldo awal kredit OpenAI US$' : 'Beli kredit OpenAI US$').number_format($usd, 2),
                    [['1400', $idr, 0, 'Kredit OpenAI', 'ai'], [$credit, 0, $idr]], 'manual', 'ai-credit:'.$id, $data['reference'] ?? null, $by->id, $data['reference'] ?? null);
                DB::table('platform_fin_ai_credits')->where('id', $id)->update(['platform_fin_entry_id' => $entry->id]);
            }
            $row = DB::table('platform_fin_ai_credits')->find($id);
            AuditEvent::log('admin', $by->id, 'ai_credit.recorded', \App\Models\PlatformFinance\Entry::find($row->platform_fin_entry_id) ?? new \App\Models\PlatformFinance\Entry(), null, (array) $row);

            return $row;
        });
    }

    public function status(): array
    {
        $credits = DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER);
        $since = (clone $credits)->min('credited_at');
        $added = (float) (clone $credits)->sum('amount_usd');
        // Biaya yang sudah dipotong dashboard saat saldo awal dicatat (titik acuan) tidak dikurangi lagi.
        $baseline = (float) (clone $credits)->sum('usage_baseline_usd');
        $used = $since ? max(0.0, (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->where('usage_date', '>=', $since)->sum('amount_usd') - $baseline) : 0.0;
        $month = (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->where('usage_date', '>=', today()->startOfMonth()->toDateString())->sum('amount_usd');
        $last7 = (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->where('usage_date', '>=', today()->subDays(7)->toDateString())->where('usage_date', '<', today()->toDateString())->sum('amount_usd');
        $balance = $since ? round($added - $used, 2) : null;
        $daily = $last7 / 7;

        return [
            'configured' => $this->configured(),
            'last_sync' => Cache::get('finance:openai:last_sync'),
            'tracking_since' => $since,
            'credits_added_usd' => round($added, 2),
            'used_since_usd' => round($used, 4),
            'balance_usd' => $balance,
            'this_month_usd' => round($month, 4),
            'avg_daily_usd_7d' => round($daily, 4),
            'days_left' => $balance !== null && $daily > 0 ? round($balance / $daily, 1) : null,
            'low_threshold_usd' => (float) config('yoka_finance.openai_low_balance_usd', 5),
            'low' => $balance !== null && $balance < (float) config('yoka_finance.openai_low_balance_usd', 5),
            'rate_idr_per_usd' => $this->rate(),
            'note' => $since ? 'Biaya persis dari Costs API OpenAI (bisa tertunda sampai sehari). Saldo = kredit yang dicatat dikurangi biaya sejak saldo awal. Dashboard OpenAI realtime, Costs API bisa telat beberapa jam: selisih kecil sementara itu normal dan hilang setelah sinkron.' : 'Catat saldo awal kredit OpenAI dulu agar saldo bisa dihitung.',
        ];
    }

    /**
     * Samakan dengan dashboard: isi saldo yang tampil di dashboard OpenAI saat ini.
     * Sinkron dulu, lalu titik acuan saldo awal disetel agar saldo = angka dashboard. Tercatat di audit.
     */
    public function calibrate(float $dashboardBalanceUsd, PlatformAdmin $by): array
    {
        if (! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif.');
        }
        if ($dashboardBalanceUsd < 0 || $dashboardBalanceUsd > 100000) {
            throw new InvalidArgumentException('Saldo dashboard tidak valid.');
        }
        $opening = DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->where('kind', 'opening')->first()
            ?? throw new InvalidArgumentException('Catat saldo awal dulu.');
        if ($this->configured()) {
            $this->sync(3);
        }
        $before = $this->status()['balance_usd'];
        $credits = (float) DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->sum('amount_usd');
        $since = DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->min('credited_at');
        $usage = (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->where('usage_date', '>=', $since)->sum('amount_usd');
        $otherBaselines = (float) DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->where('id', '!=', $opening->id)->sum('usage_baseline_usd');
        // saldo = kredit - (biaya - acuan)  =>  acuan = saldo - kredit + biaya
        $newBaseline = round($dashboardBalanceUsd - $credits + $usage - $otherBaselines, 6);
        DB::table('platform_fin_ai_credits')->where('id', $opening->id)->update(['usage_baseline_usd' => $newBaseline, 'updated_at' => now()]);
        $after = $this->status()['balance_usd'];
        AuditEvent::log('admin', $by->id, 'ai_credit.calibrated', \App\Models\PlatformFinance\Entry::find($opening->platform_fin_entry_id) ?? new \App\Models\PlatformFinance\Entry(),
            ['balance_usd' => $before, 'usage_baseline_usd' => (float) $opening->usage_baseline_usd], ['balance_usd' => $after, 'usage_baseline_usd' => $newBaseline, 'dashboard_usd' => $dashboardBalanceUsd]);

        return ['before' => $before, 'after' => $after];
    }

    /** Kurs rata-rata tertimbang dari kredit yang benar-benar dibayar (rupiah / USD). */
    public function rate(): ?float
    {
        $paid = DB::table('platform_fin_ai_credits')->where('provider', self::PROVIDER)->where('amount_idr', '>', 0);
        $usd = (float) (clone $paid)->sum('amount_usd');

        return $usd > 0 ? round((float) (clone $paid)->sum('amount_idr') / $usd, 4) : null;
    }

    /** Draf beban AI satu bulan (Dr 5100 / Cr 1400), menunggu persetujuan. Satu kali per bulan. */
    public function draftMonthlyExpense(string $period, string $actorType, int $actorId): \App\Models\PlatformFinance\Expense
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || $period >= today()->format('Y-m')) {
            throw new InvalidArgumentException('Pilih bulan yang sudah selesai.');
        }
        if (DB::table('platform_fin_ai_expensed')->where('provider', self::PROVIDER)->where('period', $period)->exists()) {
            throw new InvalidArgumentException("Beban AI {$period} sudah pernah diajukan.");
        }
        $rate = $this->rate() ?? throw new InvalidArgumentException('Catat pembelian/saldo awal kredit dengan nilai rupiah dulu agar kurs diketahui.');
        $from = Carbon::parse($period.'-01');
        $usd = (float) DB::table('platform_fin_ai_usage')->where('provider', self::PROVIDER)->whereBetween('usage_date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])->sum('amount_usd');
        if ($usd <= 0) {
            throw new InvalidArgumentException("Tidak ada biaya OpenAI tercatat untuk {$period}. Jalankan sinkron dulu.");
        }
        $idr = round($usd * $rate, 0);
        $vendor = Vendor::firstOrCreate(['name' => 'OpenAI']);

        return DB::transaction(function () use ($period, $usd, $idr, $rate, $from, $vendor, $actorType, $actorId) {
            $expense = app(ExpenseWorkflow::class)->submit([
                'expense_date' => $from->copy()->endOfMonth()->toDateString(), 'account_code' => '5100', 'paid_from' => Transactions::PREPAID,
                'amount' => $idr, 'description' => 'Pemakaian OpenAI '.$period.' US$'.number_format($usd, 2).' (kurs '.number_format($rate, 0, ',', '.').')',
                'vendor_id' => $vendor->id, 'invoice_no' => 'OPENAI-USAGE-'.$period, 'cost_center' => 'ai',
            ], $actorType, $actorId);
            DB::table('platform_fin_ai_expensed')->insert(['provider' => self::PROVIDER, 'period' => $period, 'amount_usd' => $usd, 'amount_idr' => $idr, 'platform_fin_expense_id' => $expense->id, 'created_at' => now(), 'updated_at' => now()]);

            return $expense;
        });
    }

    private function creditAccount(string $paidFrom): string
    {
        $cash = Account::whereIn('subtype', ['cash', 'bank', 'clearing'])->pluck('code')->all();

        return in_array($paidFrom, $cash, true) ? $paidFrom
            : (Transactions::OWNER_FUNDING[$paidFrom] ?? throw new InvalidArgumentException('Pilih sumber pembayaran kredit.'));
    }
}
