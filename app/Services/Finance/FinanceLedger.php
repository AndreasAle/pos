<?php

namespace App\Services\Finance;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Write side of the finance modules. Every method takes the acting user and derives
 * business_id from them; ids arriving from a request are only ever looked up inside
 * that business. Documents are voided or cancelled, never deleted.
 */
class FinanceLedger
{
    public function __construct(private InventoryService $inventory) {}

    // ── Expenses ─────────────────────────────────────────────────────────────

    public function recordExpense(User $user, array $d): Expense
    {
        $category = ExpenseCategory::where('business_id', $user->business_id)->where('is_active', true)->find($d['expense_category_id'] ?? 0)
            ?? throw ValidationException::withMessages(['expense_category_id' => 'Kategori biaya tidak valid.']);
        $outletId = $this->outlet($user, $d['outlet_id'] ?? null);
        $amount = round((float) ($d['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah harus lebih dari 0.']);
        }

        return $this->withNumber($user->business_id, 'expenses', 'expense_number', 'BY', $d['expense_date'], fn ($number) => Expense::create([
            'business_id' => $user->business_id, 'outlet_id' => $outletId, 'expense_category_id' => $category->id, 'user_id' => $user->id,
            'expense_number' => $number, 'expense_date' => $d['expense_date'], 'amount' => $amount, 'payee' => $d['payee'] ?? null,
            'payment_method' => $d['payment_method'] ?? 'cash', 'reference_no' => $d['reference_no'] ?? null, 'notes' => $d['notes'] ?? null,
        ]));
    }

    public function voidExpense(User $user, Expense $expense, string $reason): void
    {
        $this->own($user, $expense);
        if ($expense->status === 'void') {
            return;
        }
        $expense->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);
    }

    // ── Purchases / accounts payable ─────────────────────────────────────────

    /**
     * @param  array{supplier_id:int,purchase_date:string,items:array<int,array>}  $d
     */
    public function createPurchase(User $user, array $d): Purchase
    {
        $supplier = Supplier::where('business_id', $user->business_id)->find($d['supplier_id'] ?? 0)
            ?? throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak valid.']);
        $outletId = $this->outlet($user, $d['outlet_id'] ?? null);
        $lines = [];
        foreach (array_values($d['items'] ?? []) as $i => $it) {
            $qty = round((float) ($it['qty'] ?? 0), 3);
            $cost = round((float) ($it['unit_cost'] ?? 0), 2);
            if ($qty <= 0 || $cost < 0 || trim((string) ($it['description'] ?? '')) === '') {
                throw ValidationException::withMessages(["items.$i" => 'Isi nama, qty (> 0), dan harga satuan setiap baris.']);
            }
            $ingredient = null;
            if (! empty($it['ingredient_id'])) {
                $ingredient = Ingredient::where('business_id', $user->business_id)->find($it['ingredient_id'])
                    ?? throw ValidationException::withMessages(["items.$i" => 'Bahan baku tidak valid.']);
            }
            $lines[] = ['ingredient' => $ingredient, 'description' => trim($it['description']), 'qty' => $qty, 'unit' => $it['unit'] ?? $ingredient?->unit, 'cost' => $cost, 'subtotal' => round($qty * $cost, 2)];
        }
        if (! $lines) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu barang.']);
        }
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $discount = round(max(0, (float) ($d['discount_amount'] ?? 0)), 2);
        $tax = round(max(0, (float) ($d['tax_amount'] ?? 0)), 2);
        $shipping = round(max(0, (float) ($d['shipping_amount'] ?? 0)), 2);
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_amount' => 'Diskon melebihi subtotal.']);
        }
        $total = round($subtotal - $discount + $tax + $shipping, 2);
        $date = CarbonImmutable::parse($d['purchase_date']);
        $due = ! empty($d['due_date']) ? CarbonImmutable::parse($d['due_date']) : $date->addDays((int) $supplier->payment_terms_days);
        $receive = (bool) ($d['receive_stock'] ?? true);

        return DB::transaction(function () use ($user, $d, $supplier, $outletId, $lines, $subtotal, $discount, $tax, $shipping, $total, $date, $due, $receive) {
            $purchase = $this->withNumber($user->business_id, 'purchases', 'purchase_number', 'PB', $date->toDateString(), fn ($number) => Purchase::create([
                'business_id' => $user->business_id, 'outlet_id' => $outletId, 'supplier_id' => $supplier->id, 'user_id' => $user->id,
                'purchase_number' => $number, 'supplier_invoice_no' => $d['supplier_invoice_no'] ?? null, 'purchase_date' => $date->toDateString(),
                'due_date' => $due->toDateString(), 'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax,
                'shipping_amount' => $shipping, 'total' => $total, 'notes' => $d['notes'] ?? null, 'stock_received' => $receive,
            ]));
            foreach ($lines as $l) {
                PurchaseItem::create(['purchase_id' => $purchase->id, 'ingredient_id' => $l['ingredient']?->id, 'description' => $l['description'],
                    'qty' => $l['qty'], 'unit' => $l['unit'], 'unit_cost' => $l['cost'], 'subtotal' => $l['subtotal']]);
                if ($receive && $l['ingredient']) {
                    $this->inventory->addStock($l['ingredient'], $l['qty'], $l['cost'], $user, 'Pembelian '.$purchase->purchase_number);
                }
            }
            $paidNow = round((float) ($d['paid_now'] ?? 0), 2);
            if ($paidNow > 0) {
                $this->payPurchase($user, $purchase, $paidNow, $d['payment_method'] ?? 'cash', $date->toDateString(), null, 'Dibayar saat pembelian');
            }

            return $purchase->fresh(['items', 'payments', 'supplier']);
        });
    }

    public function payPurchase(User $user, Purchase $purchase, float $amount, string $method, string $paidAt, ?string $reference = null, ?string $notes = null): PurchasePayment
    {
        return DB::transaction(function () use ($user, $purchase, $amount, $method, $paidAt, $reference, $notes) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $this->own($user, $locked);
            $amount = round($amount, 2);
            if ($locked->status !== 'received') {
                throw ValidationException::withMessages(['amount' => 'Pembelian yang dibatalkan tidak dapat dibayar.']);
            }
            if ($amount <= 0 || $amount > $locked->outstanding + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus antara Rp1 dan sisa hutang (Rp'.number_format($locked->outstanding, 0, ',', '.').').']);
            }
            $payment = PurchasePayment::create(['purchase_id' => $locked->id, 'user_id' => $user->id, 'paid_at' => $paidAt, 'amount' => $amount,
                'payment_method' => $method, 'reference_no' => $reference, 'notes' => $notes]);
            $paid = round((float) $locked->paid_amount + $amount, 2);
            $locked->update(['paid_amount' => $paid, 'payment_status' => $paid + 0.001 >= (float) $locked->total ? 'paid' : 'partial']);

            return $payment;
        });
    }

    public function cancelPurchase(User $user, Purchase $purchase, string $reason): void
    {
        DB::transaction(function () use ($user, $purchase, $reason) {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->with('items')->firstOrFail();
            $this->own($user, $locked);
            if ($locked->status === 'cancelled') {
                return;
            }
            if ((float) $locked->paid_amount > 0) {
                throw ValidationException::withMessages(['reason' => 'Pembelian yang sudah memiliki pembayaran tidak dapat dibatalkan.']);
            }
            if ($locked->stock_received) {
                foreach ($locked->items as $it) {
                    if ($it->ingredient_id && ($ing = Ingredient::where('business_id', $user->business_id)->find($it->ingredient_id))) {
                        $this->inventory->addStock($ing, -1 * (float) $it->qty, 0, $user, 'Pembatalan pembelian '.$locked->purchase_number);
                    }
                }
            }
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
    }

    // ── Receivables ──────────────────────────────────────────────────────────

    public function createReceivable(User $user, array $d): Receivable
    {
        $amount = round((float) ($d['amount'] ?? 0), 2);
        if ($amount <= 0 || trim((string) ($d['party_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['amount' => 'Isi nama pihak dan jumlah piutang.']);
        }
        $customerId = null;
        if (! empty($d['customer_id'])) {
            $customerId = Customer::where('business_id', $user->business_id)->find($d['customer_id'])?->id
                ?? throw ValidationException::withMessages(['customer_id' => 'Pelanggan tidak valid.']);
        }

        return $this->withNumber($user->business_id, 'receivables', 'receivable_number', 'PT', $d['issue_date'], fn ($number) => Receivable::create([
            'business_id' => $user->business_id, 'outlet_id' => $this->outlet($user, $d['outlet_id'] ?? null), 'customer_id' => $customerId, 'user_id' => $user->id,
            'receivable_number' => $number, 'party_name' => trim($d['party_name']), 'description' => $d['description'] ?? null,
            'issue_date' => $d['issue_date'], 'due_date' => $d['due_date'] ?? null, 'amount' => $amount,
        ]));
    }

    public function payReceivable(User $user, Receivable $receivable, float $amount, string $method, string $paidAt, ?string $notes = null): ReceivablePayment
    {
        return DB::transaction(function () use ($user, $receivable, $amount, $method, $paidAt, $notes) {
            $locked = Receivable::whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            $this->own($user, $locked);
            $amount = round($amount, 2);
            if ($locked->status !== 'open' || $amount <= 0 || $amount > $locked->outstanding + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Jumlah harus antara Rp1 dan sisa piutang (Rp'.number_format($locked->outstanding, 0, ',', '.').').']);
            }
            $payment = ReceivablePayment::create(['receivable_id' => $locked->id, 'user_id' => $user->id, 'paid_at' => $paidAt, 'amount' => $amount, 'payment_method' => $method, 'notes' => $notes]);
            $locked->update(['paid_amount' => round((float) $locked->paid_amount + $amount, 2)]);

            return $payment;
        });
    }

    public function voidReceivable(User $user, Receivable $receivable, string $reason): void
    {
        $this->own($user, $receivable);
        if ((float) $receivable->paid_amount > 0) {
            throw ValidationException::withMessages(['reason' => 'Piutang yang sudah ada pembayaran tidak dapat dibatalkan.']);
        }
        $receivable->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function own(User $user, $model): void
    {
        if ((int) $model->business_id !== (int) $user->business_id) {
            abort(404);
        }
    }

    private function outlet(User $user, mixed $outletId): ?int
    {
        if (empty($outletId)) {
            return $user->role !== 'owner' && $user->outlet_id ? (int) $user->outlet_id : null;
        }
        $ok = $user->business->outlets()->where('id', $outletId)->when($user->role !== 'owner' && $user->outlet_id, fn ($q) => $q->where('id', $user->outlet_id))->exists();

        return $ok ? (int) $outletId : throw ValidationException::withMessages(['outlet_id' => 'Outlet tidak tersedia dalam akses kamu.']);
    }

    /** PREFIX-yymmdd-NNN, unique per business; retried if two requests race for the same number. */
    private function withNumber(int $businessId, string $table, string $column, string $prefix, string $date, \Closure $create)
    {
        $stem = $prefix.'-'.CarbonImmutable::parse($date)->format('ymd').'-';
        for ($try = 0; $try < 5; $try++) {
            $seq = DB::table($table)->where('business_id', $businessId)->where($column, 'like', $stem.'%')->count() + 1 + $try;
            try {
                return $create($stem.str_pad((string) $seq, 3, '0', STR_PAD_LEFT));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }
        throw new \RuntimeException('Gagal membuat nomor dokumen.');
    }
}
