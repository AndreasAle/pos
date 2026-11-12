<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Services\Finance\FinanceLedger;
use App\Services\Finance\FinanceReports;
use App\Services\Reporting\SalesReportFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Biaya, Supplier, Pembelian, Hutang, Piutang. Owner/admin only (route middleware).
 * Every lookup is scoped to the signed-in user's business.
 */
class FinanceLedgerController extends Controller
{
    private const METHODS = ['cash', 'transfer', 'qris', 'ewallet', 'debit', 'other'];

    public function __construct(private FinanceLedger $ledger, private FinanceReports $reports) {}

    private function biz(): int
    {
        return (int) auth()->user()->business_id;
    }

    private function outlets()
    {
        $ids = SalesReportFilter::allowedOutlets(auth()->user());

        return auth()->user()->business->outlets()->whereIn('id', $ids)->get(['id', 'name']);
    }

    // ── Biaya ────────────────────────────────────────────────────────────────

    public function expenses(Request $request)
    {
        ExpenseCategory::ensureDefaults($this->biz());
        try {
            $f = SalesReportFilter::fromRequest($request->user(), $request->query());
        } catch (ValidationException $e) {
            return redirect()->route('expenses.index')->with('report_error', collect($e->errors())->flatten()->first());
        }
        $base = $this->reports->expenseQuery($f)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        $list = Expense::query()->where('business_id', $this->biz())->where('expense_date', '>=', $f->dateFrom())->where('expense_date', '<=', $f->dateTo())
            ->when($request->query('category'), fn ($q, $c) => $q->where('expense_category_id', (int) $c))
            ->when($request->query('status') === 'void', fn ($q) => $q->where('status', 'void'), fn ($q) => $q->where('status', 'posted'))
            ->where(fn ($q) => $q->whereIn('outlet_id', $f->outletIds)->when(count($f->outletIds) === count($f->allowedOutletIds), fn ($w) => $w->orWhereNull('outlet_id')))
            ->with(['category:id,code,name', 'outlet:id,name'])->orderByDesc('expense_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('finance.expenses.index', [
            'f' => $f, 'expenses' => $list, 'totals' => $this->reports->expenseTotals($f), 'outlets' => $this->outlets(), 'queryString' => $f->toQuery(),
            'categories' => ExpenseCategory::where('business_id', $this->biz())->orderBy('code')->get(),
        ]);
    }

    public function expenseCreate()
    {
        ExpenseCategory::ensureDefaults($this->biz());

        return view('finance.expenses.create', ['categories' => ExpenseCategory::where('business_id', $this->biz())->where('is_active', true)->orderBy('code')->get(), 'outlets' => $this->outlets(), 'methods' => self::METHODS]);
    }

    public function expenseStore(Request $request)
    {
        $d = $request->validate([
            'expense_category_id' => 'required|integer', 'expense_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required|numeric|min:1|max:999999999999',
            'payee' => 'nullable|string|max:255', 'payment_method' => ['required', Rule::in(self::METHODS)], 'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000', 'outlet_id' => 'nullable|integer',
        ]);
        $this->ledger->recordExpense($request->user(), $d);

        return redirect()->route('expenses.index')->with('success', 'Biaya dicatat.');
    }

    public function expenseVoid(Request $request, Expense $expense)
    {
        $d = $request->validate(['reason' => 'required|string|max:255']);
        $this->ledger->voidExpense($request->user(), $expense, $d['reason']);

        return back()->with('success', 'Biaya dibatalkan (tetap tercatat sebagai void).');
    }

    public function categories()
    {
        ExpenseCategory::ensureDefaults($this->biz());

        return view('finance.expenses.categories', ['categories' => ExpenseCategory::where('business_id', $this->biz())->withCount('expenses')->orderBy('code')->get()]);
    }

    public function categoryStore(Request $request)
    {
        $d = $request->validate(['code' => ['required', 'regex:/^[0-9]{4}$/', Rule::unique('expense_categories')->where('business_id', $this->biz())], 'name' => 'required|string|max:100', 'group' => ['required', Rule::in(['operating', 'other_expense', 'tax'])]]);
        ExpenseCategory::create($d + ['business_id' => $this->biz()]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function categoryToggle(ExpenseCategory $category)
    {
        abort_unless((int) $category->business_id === $this->biz(), 404);
        $category->update(['is_active' => ! $category->is_active]);

        return back();
    }

    // ── Supplier ─────────────────────────────────────────────────────────────

    public function suppliers()
    {
        return view('finance.suppliers', ['suppliers' => $this->suppliersWithTotals(), 'edit' => null]);
    }

    public function supplierEdit(Supplier $supplier)
    {
        abort_unless((int) $supplier->business_id === $this->biz(), 404);

        return view('finance.suppliers', ['suppliers' => $this->suppliersWithTotals(), 'edit' => $supplier]);
    }

    public function supplierStore(Request $request, ?Supplier $supplier = null)
    {
        $d = $request->validate(['name' => 'required|string|max:255', 'contact_person' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500', 'tax_id' => 'nullable|string|max:40', 'payment_terms_days' => 'nullable|integer|min:0|max:365']);
        $d['payment_terms_days'] = (int) ($d['payment_terms_days'] ?? 0);
        if ($supplier) {
            abort_unless((int) $supplier->business_id === $this->biz(), 404);
            $supplier->update($d + ['is_active' => $request->boolean('is_active', true)]);
        } else {
            Supplier::create($d + ['business_id' => $this->biz()]);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier disimpan.');
    }

    // ── Pembelian ────────────────────────────────────────────────────────────

    public function purchases(Request $request)
    {
        $q = Purchase::where('business_id', $this->biz())->with('supplier:id,name')
            ->when($request->query('supplier'), fn ($b, $v) => $b->where('supplier_id', (int) $v))
            ->when(in_array($request->query('payment'), ['unpaid', 'partial', 'paid'], true), fn ($b) => $b->where('payment_status', $request->query('payment'))->where('status', 'received'))
            ->when($request->filled('q'), fn ($b) => $b->where(fn ($w) => $w->where('purchase_number', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->query('q')).'%')->orWhere('supplier_invoice_no', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->query('q')).'%')))
            ->orderByDesc('purchase_date')->orderByDesc('id');

        return view('finance.purchases.index', [
            'purchases' => $q->paginate(25)->withQueryString(),
            'suppliers' => Supplier::where('business_id', $this->biz())->orderBy('name')->get(['id', 'name']),
            'stats'     => $this->purchaseStats(),
        ]);
    }

    public function purchaseCreate()
    {
        return view('finance.purchases.create', [
            'suppliers' => Supplier::where('business_id', $this->biz())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'payment_terms_days']),
            'ingredients' => Ingredient::where('business_id', $this->biz())->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit', 'average_cost']),
            'outlets' => $this->outlets(), 'methods' => self::METHODS,
        ]);
    }

    public function purchaseStore(Request $request)
    {
        $d = $request->validate([
            'supplier_id' => 'required|integer', 'purchase_date' => 'required|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:purchase_date', 'supplier_invoice_no' => 'nullable|string|max:100',
            'outlet_id' => 'nullable|integer', 'discount_amount' => 'nullable|numeric|min:0', 'tax_amount' => 'nullable|numeric|min:0', 'shipping_amount' => 'nullable|numeric|min:0',
            'paid_now' => 'nullable|numeric|min:0', 'payment_method' => ['nullable', Rule::in(self::METHODS)], 'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:100', 'items.*.description' => 'required|string|max:255', 'items.*.qty' => 'required|numeric|min:0.001', 'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:20', 'items.*.ingredient_id' => 'nullable|integer',
        ]);
        $d['receive_stock'] = $request->boolean('receive_stock');
        $p = $this->ledger->createPurchase($request->user(), $d);

        return redirect()->route('purchases.show', $p)->with('success', 'Pembelian dicatat.');
    }

    public function purchaseShow(Purchase $purchase)
    {
        abort_unless((int) $purchase->business_id === $this->biz(), 404);

        return view('finance.purchases.show', ['purchase' => $purchase->load(['items.ingredient:id,name', 'payments', 'supplier', 'outlet:id,name', 'user:id,name']), 'methods' => self::METHODS]);
    }

    public function purchasePay(Request $request, Purchase $purchase)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:1', 'payment_method' => ['required', Rule::in(self::METHODS)], 'paid_at' => 'required|date_format:Y-m-d', 'reference_no' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:255']);
        $this->ledger->payPurchase($request->user(), $purchase, (float) $d['amount'], $d['payment_method'], $d['paid_at'], $d['reference_no'] ?? null, $d['notes'] ?? null);

        return back()->with('success', 'Pembayaran hutang dicatat.');
    }

    public function purchaseCancel(Request $request, Purchase $purchase)
    {
        $d = $request->validate(['reason' => 'required|string|max:255']);
        $this->ledger->cancelPurchase($request->user(), $purchase, $d['reason']);

        return back()->with('success', 'Pembelian dibatalkan.');
    }

    // ── Hutang & Piutang ─────────────────────────────────────────────────────

    public function payables()
    {
        return view('finance.payables', ['aging' => $this->reports->payablesAging($this->biz()), 'labels' => FinanceReports::BUCKETS]);
    }

    public function receivables(Request $request)
    {
        $list = Receivable::where('business_id', $this->biz())->when($request->query('status') === 'void', fn ($q) => $q->where('status', 'void'), fn ($q) => $q->where('status', 'open'))
            ->orderByDesc('issue_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('finance.receivables.index', ['receivables' => $list, 'aging' => $this->reports->receivablesAging($this->biz()), 'labels' => FinanceReports::BUCKETS]);
    }

    public function receivableCreate()
    {
        return view('finance.receivables.create', ['customers' => Customer::where('business_id', $this->biz())->orderBy('name')->get(['id', 'name']), 'outlets' => $this->outlets()]);
    }

    public function receivableStore(Request $request)
    {
        $d = $request->validate(['party_name' => 'required|string|max:255', 'customer_id' => 'nullable|integer', 'description' => 'nullable|string|max:255', 'issue_date' => 'required|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:issue_date', 'amount' => 'required|numeric|min:1|max:999999999999', 'outlet_id' => 'nullable|integer']);
        $r = $this->ledger->createReceivable($request->user(), $d);

        return redirect()->route('receivables.show', $r)->with('success', 'Piutang dicatat.');
    }

    public function receivableShow(Receivable $receivable)
    {
        abort_unless((int) $receivable->business_id === $this->biz(), 404);

        return view('finance.receivables.show', ['receivable' => $receivable->load(['payments', 'customer:id,name']), 'methods' => self::METHODS]);
    }

    public function receivablePay(Request $request, Receivable $receivable)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:1', 'payment_method' => ['required', Rule::in(self::METHODS)], 'paid_at' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:255']);
        $this->ledger->payReceivable($request->user(), $receivable, (float) $d['amount'], $d['payment_method'], $d['paid_at'], $d['notes'] ?? null);

        return back()->with('success', 'Penerimaan piutang dicatat.');
    }

    public function receivableVoid(Request $request, Receivable $receivable)
    {
        $d = $request->validate(['reason' => 'required|string|max:255']);
        $this->ledger->voidReceivable($request->user(), $receivable, $d['reason']);

        return back()->with('success', 'Piutang dibatalkan.');
    }

    /** Daftar supplier lengkap dengan jumlah, total, dan nilai terbayar pembelian (tanpa yang dibatalkan). */
    private function suppliersWithTotals()
    {
        $received = fn ($q) => $q->where('status', 'received');

        return Supplier::where('business_id', $this->biz())
            ->withCount('purchases')
            ->withSum(['purchases as purchases_total' => $received], 'total')
            ->withSum(['purchases as purchases_paid' => $received], 'paid_amount')
            ->orderBy('name')->get();
    }

    /** Angka ringkas di atas daftar pembelian. */
    private function purchaseStats(): array
    {
        $base = fn () => Purchase::where('business_id', $this->biz())->where('status', 'received');
        $month = $base()->whereBetween('purchase_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $open = $base()->whereColumn('paid_amount', '<', 'total')->get(['total', 'paid_amount', 'due_date']);
        $overdue = $open->filter(fn ($p) => $p->due_date && $p->due_date->endOfDay()->isPast());

        return [
            'month_total'    => (float) (clone $month)->sum('total'),
            'month_count'    => (clone $month)->count(),
            'outstanding'    => (float) $open->sum(fn ($p) => (float) $p->total - (float) $p->paid_amount),
            'overdue_count'  => $overdue->count(),
            'overdue_amount' => (float) $overdue->sum(fn ($p) => (float) $p->total - (float) $p->paid_amount),
        ];
    }
}
