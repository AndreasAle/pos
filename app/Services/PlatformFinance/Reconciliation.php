<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Line;
use App\Models\PlatformFinance\StatementLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Rekonsiliasi bank/Midtrans per transaksi (Financial Accounting bab 8.6, bank reconciliation).
 *
 * Mutasi dari rekening diimpor (CSV), lalu tiap baris dicocokkan ke baris jurnal pada akun yang sama dengan
 * nominal dan arah yang PERSIS sama. Selisih tidak pernah "dipaksa cocok": selisih harus dicatat dulu sebagai jurnal
 * (mis. biaya admin bank), baru dicocokkan. Tidak ada yang otomatis membuat jurnal.
 */
class Reconciliation
{
    public const MAX_ROWS = 2000;

    public const DATE_WINDOW_DAYS = 3;

    public function __construct(private Ledger $ledger) {}

    /** @return array{imported:int,duplicates:int,errors:array<int,string>,batch:string} */
    public function import(string $accountCode, string $csv, PlatformAdmin $by): array
    {
        $this->assertFinance($by);
        $this->assertReconcilable($accountCode);
        $rows = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($csv)), fn ($r) => trim($r) !== ''));
        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_ROWS.' baris per impor.');
        }
        $delimiter = substr_count($rows[0] ?? '', ';') > substr_count($rows[0] ?? '', ',') ? ';' : ',';
        $batch = now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $seen = [];
        $parsed = [];
        $errors = [];

        foreach ($rows as $i => $raw) {
            $cols = array_map('trim', str_getcsv($raw, $delimiter));
            if ($i === 0 && ! $this->parseDate($cols[0] ?? '')) {
                continue; // baris judul
            }
            $date = $this->parseDate($cols[0] ?? '');
            $amount = $this->parseAmount($cols[2] ?? '');
            if (! $date || $amount === null || $amount == 0.0 || ($cols[1] ?? '') === '') {
                $errors[] = 'Baris '.($i + 1).': format harus tanggal, keterangan, nominal (+ masuk / - keluar), referensi opsional.';

                continue;
            }
            if ($date->isAfter(today())) {
                $errors[] = 'Baris '.($i + 1).': tanggal di masa depan.';

                continue;
            }
            $ref = ($cols[3] ?? '') !== '' ? mb_substr($cols[3], 0, 120) : null;
            $key = implode('|', [$accountCode, $date->toDateString(), number_format($amount, 2, '.', ''), mb_strtolower($cols[1]), $ref]);
            $seen[$key] = ($seen[$key] ?? 0) + 1; // dua transfer identik di hari yang sama tetap dua baris
            $parsed[] = ['txn_date' => $date, 'description' => mb_substr($cols[1], 0, 200), 'amount' => $amount, 'reference' => $ref, 'fingerprint' => hash('sha256', $key.'#'.$seen[$key])];
        }

        $existing = StatementLine::whereIn('fingerprint', array_column($parsed, 'fingerprint'))->pluck('fingerprint')->flip();
        $new = array_values(array_filter($parsed, fn ($p) => ! isset($existing[$p['fingerprint']])));

        DB::transaction(function () use ($new, $accountCode, $batch, $by) {
            foreach ($new as $p) {
                StatementLine::create($p + ['account_code' => $accountCode, 'import_batch' => $batch, 'imported_by' => $by->id]);
            }
            if ($new !== []) {
                AuditEvent::log('admin', $by->id, 'statement.imported', StatementLine::where('import_batch', $batch)->first(), null, ['account' => $accountCode, 'batch' => $batch, 'rows' => count($new)]);
            }
        });

        return ['imported' => count($new), 'duplicates' => count($parsed) - count($new), 'errors' => array_slice($errors, 0, 20), 'batch' => $batch];
    }

    /** Cocokkan otomatis hanya bila jelas: referensi sama, atau satu-satunya kandidat terdekat dalam ±3 hari. */
    public function autoMatch(string $accountCode, ?PlatformAdmin $by = null): int
    {
        $this->assertReconcilable($accountCode);
        $matched = 0;
        foreach (StatementLine::where('account_code', $accountCode)->where('status', 'unmatched')->orderBy('txn_date')->orderBy('id')->get() as $s) {
            $candidates = $this->candidates($s);
            if ($candidates->isEmpty()) {
                continue;
            }
            $byRef = $s->reference ? $candidates->filter(fn (Line $l) => $l->entry->reference === $s->reference) : collect();
            if ($byRef->count() === 1) {
                $pick = $byRef->first();
            } else {
                $best = $candidates->min(fn (Line $l) => $this->dayGap($s, $l));
                $nearest = $candidates->filter(fn (Line $l) => $this->dayGap($s, $l) === $best);
                if ($nearest->count() !== 1) {
                    continue; // ragu: biarkan manusia memilih
                }
                $pick = $nearest->first();
            }
            $s->update(['status' => 'matched', 'platform_fin_line_id' => $pick->id, 'match_method' => 'auto', 'resolved_by' => $by?->id, 'resolved_at' => now()]);
            $matched++;
        }
        if ($matched && $by) {
            AuditEvent::log('admin', $by->id, 'statement.auto_matched', StatementLine::where('account_code', $accountCode)->where('match_method', 'auto')->latest('id')->first(), null, ['account' => $accountCode, 'matched' => $matched]);
        }

        return $matched;
    }

    /** Kandidat jurnal untuk satu baris mutasi: akun sama, arah dan nominal sama persis, ±3 hari, belum dicocokkan, bukan jurnal yang dibalik. */
    public function candidates(StatementLine $s, int $window = self::DATE_WINDOW_DAYS): Collection
    {
        $accountId = Account::where('code', $s->account_code)->value('id');
        $amount = abs((float) $s->amount);

        return Line::with('entry')->where('platform_fin_account_id', $accountId)
            ->where($s->amount > 0 ? 'debit' : 'credit', $amount)
            ->whereNotIn('id', StatementLine::whereNotNull('platform_fin_line_id')->select('platform_fin_line_id'))
            ->whereHas('entry', fn ($q) => $q->whereNull('reversed_by_id')->whereNull('reversal_of_id')
                ->whereBetween('entry_date', [$s->txn_date->copy()->subDays($window)->toDateString(), $s->txn_date->copy()->addDays($window)->toDateString()]))
            ->get();
    }

    public function match(StatementLine $s, int $lineId, PlatformAdmin $by, ?string $note = null): StatementLine
    {
        $this->assertFinance($by);

        return DB::transaction(function () use ($s, $lineId, $by, $note) {
            $locked = StatementLine::lockForUpdate()->findOrFail($s->id);
            if ($locked->status !== 'unmatched') {
                throw new InvalidArgumentException('Mutasi ini sudah diproses.');
            }
            if (! $this->candidates($locked, 31)->contains('id', $lineId)) {
                throw new InvalidArgumentException('Jurnal tidak cocok: akun, arah, dan nominal harus sama persis (maks. selisih 31 hari) dan belum dicocokkan. Catat selisihnya sebagai jurnal dulu.');
            }
            $locked->update(['status' => 'matched', 'platform_fin_line_id' => $lineId, 'match_method' => 'manual', 'note' => $note ? mb_substr($note, 0, 200) : null, 'resolved_by' => $by->id, 'resolved_at' => now()]);
            AuditEvent::log('admin', $by->id, 'statement.matched', $locked, ['status' => 'unmatched'], ['status' => 'matched', 'line_id' => $lineId], $note);

            return $locked;
        });
    }

    public function ignore(StatementLine $s, PlatformAdmin $by, string $reason): StatementLine
    {
        $this->assertFinance($by);
        if (mb_strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException('Alasan mengabaikan minimal 5 karakter.');
        }

        return DB::transaction(function () use ($s, $by, $reason) {
            $locked = StatementLine::lockForUpdate()->findOrFail($s->id);
            if ($locked->status !== 'unmatched') {
                throw new InvalidArgumentException('Mutasi ini sudah diproses.');
            }
            $locked->update(['status' => 'ignored', 'note' => mb_substr(trim($reason), 0, 200), 'resolved_by' => $by->id, 'resolved_at' => now()]);
            AuditEvent::log('admin', $by->id, 'statement.ignored', $locked, ['status' => 'unmatched'], ['status' => 'ignored'], $reason);

            return $locked;
        });
    }

    public function unmatch(StatementLine $s, PlatformAdmin $by, string $reason): StatementLine
    {
        $this->assertFinance($by);

        return DB::transaction(function () use ($s, $by, $reason) {
            $locked = StatementLine::lockForUpdate()->findOrFail($s->id);
            if ($locked->status === 'unmatched') {
                throw new InvalidArgumentException('Mutasi ini belum diproses.');
            }
            $before = $locked->only(['status', 'platform_fin_line_id', 'match_method', 'note']);
            $locked->update(['status' => 'unmatched', 'platform_fin_line_id' => null, 'match_method' => null, 'note' => null, 'resolved_by' => null, 'resolved_at' => null]);
            AuditEvent::log('admin', $by->id, 'statement.unmatched', $locked, $before, ['status' => 'unmatched'], $reason);

            return $locked;
        });
    }

    /**
     * Laporan rekonsiliasi per akun:
     * saldo rekening - mutasi yang belum ada di buku + jurnal yang belum muncul di rekening = saldo buku.
     */
    public function summary(string $accountCode, ?Carbon $asOf = null, ?float $statementClosing = null): array
    {
        $this->assertReconcilable($accountCode);
        $asOf ??= today();
        $ledgerBalance = (float) ($this->ledger->balances(null, $asOf)[$accountCode]['balance'] ?? 0);
        $stmt = StatementLine::where('account_code', $accountCode)->where('txn_date', '<=', $asOf->toDateString());
        $firstDate = (clone $stmt)->min('txn_date');
        $notInBooks = (clone $stmt)->where('status', 'unmatched')->orderBy('txn_date')->get();

        $accountId = Account::where('code', $accountCode)->value('id');
        $notInBank = $firstDate ? Line::with('entry')->where('platform_fin_account_id', $accountId)
            ->whereNotIn('id', StatementLine::whereNotNull('platform_fin_line_id')->select('platform_fin_line_id'))
            ->whereHas('entry', fn ($q) => $q->whereNull('reversed_by_id')->whereNull('reversal_of_id')->whereBetween('entry_date', [$firstDate, $asOf->toDateString()]))
            ->get() : collect();

        $notInBooksNet = round((float) $notInBooks->sum('amount'), 2);
        $notInBankNet = round((float) $notInBank->sum(fn (Line $l) => (float) $l->debit - (float) $l->credit), 2);
        $adjusted = $statementClosing === null ? null : round($statementClosing - $notInBooksNet + $notInBankNet, 2);

        return [
            'account' => $accountCode,
            'as_of' => $asOf->toDateString(),
            'ledger_balance' => $ledgerBalance,
            'statement_closing' => $statementClosing,
            'statement_lines' => (clone $stmt)->count(),
            'matched' => (clone $stmt)->where('status', 'matched')->count(),
            'ignored' => (clone $stmt)->where('status', 'ignored')->count(),
            'not_in_books' => $notInBooks->map(fn ($s) => ['id' => $s->id, 'date' => $s->txn_date->toDateString(), 'description' => $s->description, 'amount' => (float) $s->amount, 'reference' => $s->reference])->all(),
            'not_in_books_net' => $notInBooksNet,
            'not_in_bank' => $notInBank->map(fn (Line $l) => ['line_id' => $l->id, 'date' => $l->entry->entry_date->toDateString(), 'description' => $l->entry->description, 'amount' => (float) $l->debit - (float) $l->credit])->values()->all(),
            'not_in_bank_net' => $notInBankNet,
            'adjusted_statement_balance' => $adjusted,
            'difference' => $adjusted === null ? null : round($adjusted - $ledgerBalance, 2),
            'reconciled' => $adjusted === null ? null : abs($adjusted - $ledgerBalance) < 0.005 && $notInBooks->isEmpty(),
        ];
    }

    /** Jumlah mutasi belum diproses dalam rentang tanggal (dipakai checklist tutup buku dan Nanda). */
    public function unmatchedCount(?Carbon $from = null, ?Carbon $to = null): int
    {
        return StatementLine::where('status', 'unmatched')
            ->when($from, fn ($q) => $q->where('txn_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('txn_date', '<=', $to->toDateString()))->count();
    }

    private function dayGap(StatementLine $s, Line $l): int
    {
        return (int) abs($s->txn_date->diffInDays($l->entry->entry_date));
    }

    private function parseDate(string $v): ?Carbon
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $v);
            if ($d && $d->format($f) === $v) {
                return Carbon::instance($d)->startOfDay();
            }
        }

        return null;
    }

    /** "1.250.000", "-150000", "Rp 1.250.000,50", "1250000.50" → float. */
    public function parseAmount(string $v): ?float
    {
        $v = str_ireplace(['rp', ' ', "\u{00A0}"], '', trim($v));
        $negative = str_starts_with($v, '-') || (str_starts_with($v, '(') && str_ends_with($v, ')'));
        $v = trim($v, '-()+');
        if ($v === '' || ! preg_match('/^[\d.,]+$/', $v)) {
            return null;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v); // format Indonesia: titik ribuan, koma desimal
        } elseif (substr_count($v, '.') > 1 || preg_match('/\.\d{3}$/', $v)) {
            $v = str_replace('.', '', $v); // titik sebagai ribuan
        }
        if (! is_numeric($v)) {
            return null;
        }

        return round(($negative ? -1 : 1) * (float) $v, 2);
    }

    private function assertReconcilable(string $code): void
    {
        if (! Account::where('code', $code)->whereIn('subtype', ['cash', 'bank', 'clearing'])->exists()) {
            throw new InvalidArgumentException('Rekonsiliasi hanya untuk akun kas, bank, atau Midtrans.');
        }
    }

    private function assertFinance(PlatformAdmin $by): void
    {
        if (! $by->is_active || ! $by->canViewFinancials()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif.');
        }
    }
}
