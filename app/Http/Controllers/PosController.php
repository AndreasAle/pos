<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\BalanceService;
use App\Services\PosOrderService;
use App\Services\QrisService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    public function __construct(
        protected PosOrderService $posService,
        protected BalanceService $balance,
        protected QrisService $qris,
    ) {}

    /**
     * Build a dynamic QRIS for the amount on screen.
     *
     * The customer scans and their banking app shows the exact total, so the
     * cashier never has to read it out. Payment lands straight in the merchant
     * account; the cashier confirms receipt to close the sale.
     */
    public function dynamicQris(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $settings = auth()->user()->business->settings ?? [];
        $amount   = (int) round($validated['amount']);

        $payload = $this->qris->toDynamic($settings['qris_payload'] ?? '', $amount);

        return response()->json([
            'success'       => true,
            'amount'        => $amount,
            'svg'           => $this->qris->svg($payload),
            'merchant_name' => $this->qris->merchantName($payload),
        ]);
    }

    public function index()
    {
        $user        = auth()->user();
        $business    = $user->business;
        $activeShift = $user->activeShift()->with('outlet')->first();

        if (!$activeShift && $user->role === 'cashier') {
            return redirect()->route('shifts.open')
                ->with('info', 'Buka shift terlebih dahulu sebelum memulai transaksi.');
        }

        $outletId = $activeShift?->outlet_id ?? $user->outlet_id;

        $categories = ProductCategory::forBusiness($business->id)
            ->active()
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $rawProducts = Product::forBusiness($business->id)
            ->active()
            ->with([
                'variants' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'addons'   => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->when($outletId, fn($q) => $q->forOutletOrGlobal($outletId))
            ->orderBy('sort_order')
            ->get();

        $productsJson = $rawProducts->map(fn($p) => [
            'id'         => $p->id,
            'cat_id'     => $p->product_category_id,
            'name'       => $p->name,
            'sku'        => $p->sku,
            'price'      => (float) $p->price,
            'cost_price' => (float) $p->cost_price,
            'image'      => $p->image_url,
            'variants'   => $p->variants->map(fn($v) => [
                'id'               => $v->id,
                'name'             => $v->name,
                'price_adjustment' => (float) $v->price_adjustment,
            ])->values()->all(),
            'addons' => $p->addons->map(fn($a) => [
                'id'    => $a->id,
                'name'  => $a->name,
                'price' => (float) $a->price,
            ])->values()->all(),
        ])->values();

        $rawBundles  = ProductBundle::forBusiness($business->id)
            ->active()
            ->with('items.product')
            ->get();

        $bundlesJson = $rawBundles->map(fn($b) => [
            'id'          => $b->id,
            'name'        => $b->name,
            'price'       => (float) $b->price,
            'description' => $b->description,
            'items'       => $b->items->map(fn($i) => [
                'product_name' => optional($i->product)->name,
                'qty'          => (float) $i->qty,
            ])->values()->all(),
        ])->values();

        $promotions = Promotion::forBusiness($business->id)->active()->get();
        $settings   = $business->settings ?? [];

        // A stored QRIS payload lets us build a dynamic QR ourselves — no gateway,
        // no webhook — so it takes priority over Midtrans when both are present.
        $qrisPayload   = trim($settings['qris_payload'] ?? '');
        $qrisDynamic   = $qrisPayload !== '' && $this->qris->isValid($qrisPayload);

        $midtransEnabled   = !$qrisDynamic && config('midtrans.server_key') !== '';
        $midtransClientKey = config('midtrans.client_key');

        $qrisData = [
            'image'         => $business->qris_image
                                ? asset('storage/' . $business->qris_image)
                                : null,
            'merchant_name' => $business->qris_merchant_name
                                ?: ($qrisDynamic ? $this->qris->merchantName($qrisPayload) : null)
                                ?: $business->name,
            'nmid'          => $business->qris_nmid ?? '',
            'is_set'        => (bool) $business->qris_image || $midtransEnabled || $qrisDynamic,
            'use_midtrans'  => $midtransEnabled,
            'use_dynamic'   => $qrisDynamic,
        ];

        return view('pos.index', compact(
            'categories', 'productsJson', 'bundlesJson',
            'promotions', 'activeShift', 'settings', 'business',
            'qrisData', 'midtransClientKey', 'midtransEnabled'
        ));
    }

    /**
     * Shape rules for a cart line. Prices are deliberately absent — the service
     * reads them from the database, so anything sent here would be ignored.
     *
     * @return array<string, mixed>
     */
    private function cartRules(): array
    {
        return [
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required_without:items.*.bundle_id|nullable|integer|exists:products,id',
            'items.*.bundle_id'        => 'required_without:items.*.product_id|nullable|integer|exists:product_bundles,id',
            'items.*.qty'              => 'required|numeric|min:0.001',
            'items.*.variant_id'       => 'nullable|integer|exists:product_variants,id',
            'items.*.notes'            => 'nullable|string|max:255',
            'items.*.addons'           => 'nullable|array',
            'items.*.addons.*.addon_id' => 'required|integer|exists:product_addons,id',
            'order_type'               => 'nullable|in:dine_in,takeaway,delivery',
            'customer_id'              => 'nullable|integer|exists:customers,id',
            'promotion_id'             => 'nullable|integer|exists:promotions,id',
            'manual_discount'          => 'nullable|numeric|min:0',
            'redeem_points'            => 'nullable|integer|min:0',
            'delivery_fee'             => 'nullable|numeric|min:0',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->cartRules());

        // Validate payment: either split_payments array OR single payment_method+paid_amount
        if ($request->has('split_payments')) {
            $request->validate([
                'split_payments'          => 'required|array|min:1',
                'split_payments.*.method' => 'required|in:cash,qris,transfer,ewallet,debit,other',
                'split_payments.*.amount' => 'required|numeric|min:0',
            ]);
        } else {
            $request->validate([
                'payment_method' => 'required|in:cash,qris,transfer,ewallet,debit,other',
                'paid_amount'    => 'required|numeric|min:0',
            ]);
        }

        $order = $this->posService->createOrder(auth()->user(), $request->all());

        // Send WhatsApp receipt if enabled
        $this->sendWhatsAppReceipt($order);

        return response()->json([
            'success'      => true,
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'receipt_url'  => route('receipt.show', $order),
            // Authoritative totals. The cart on screen is only a preview — prices
            // are resolved server-side, so these are the numbers that count.
            'grand_total'  => (float) $order->grand_total,
            'change'       => (float) $order->change_amount,
            // Can be lower than what the cashier typed: points are only spent up
            // to what the bill can absorb.
            'points_redeemed' => (int) abs(
                CustomerPoint::where('order_id', $order->id)->where('type', 'redeem')->sum('points')
            ),
        ]);
    }

    /**
     * Create a pending QRIS order (before payment is confirmed via Midtrans).
     * Returns order_id so the POS can call Midtrans QRIS API.
     */
    public function createQrisDraft(Request $request)
    {
        $request->validate($this->cartRules());

        $data = array_merge($request->all(), [
            'payment_method' => 'qris',
            'paid_amount'    => 0,
        ]);

        $order = $this->posService->createOrder(auth()->user(), $data, paymentPending: true);

        return response()->json([
            'success'      => true,
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'grand_total'  => (int) $order->grand_total,
        ]);
    }

    /**
     * Manually confirm a QRIS order as paid (fallback when polling times out).
     */
    public function confirmQrisManual(Request $request, Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        abort_if($order->payment_status === 'paid', 422, 'Order sudah dibayar.');

        $order->update([
            'payment_status' => 'paid',
            'status'         => 'paid',
            'paid_amount'    => $order->grand_total,
        ]);

        // Credit the merchant now. Without this the balance was lost for good:
        // the webhook that arrives later skips crediting because the order is
        // already marked paid. Idempotent, so the later webhook is harmless.
        try {
            $this->balance->creditFromQris($order->load('business'));
        } catch (\Throwable $e) {
            Log::error('Balance credit failed on manual QRIS confirm', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }

        // Handle loyalty points now (deferred from QRIS draft creation)
        $settings = $order->business->settings ?? [];
        if ($order->customer_id) {
            $customer = \App\Models\Customer::find($order->customer_id);
            if ($customer) {
                $customer->increment('total_transactions');
                $customer->increment('total_spending', $order->grand_total);

                $ppr = (float) ($settings['points_per_rupiah'] ?? 0);
                if ($ppr > 0) {
                    $earned = (int) floor($order->grand_total * $ppr);
                    if ($earned > 0) {
                        $customer->increment('loyalty_points', $earned);
                        $customer->points()->create([
                            'order_id'    => $order->id,
                            'type'        => 'earn',
                            'points'      => $earned,
                            'description' => 'Poin dari transaksi ' . $order->order_number,
                        ]);
                    }
                }
            }
        }

        $order->loadMissing(['items.addons', 'business']);
        $this->sendWhatsAppReceipt($order);

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'receipt_url'  => route('receipt.show', $order),
        ]);
    }

    public function hold(Request $request)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:0.001',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $order = $this->posService->holdOrder(auth()->user(), $request->all());

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }

    public function drafts()
    {
        $drafts = Order::forBusiness(auth()->user()->business_id)
            ->where('status', 'draft')
            ->with('items')
            ->latest()
            ->get();

        return response()->json($drafts);
    }

    public function loadDraft(Request $request, Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        abort_if($order->status !== 'draft', 422);

        $order->load('items.addons', 'items.product', 'items.variant');
        return response()->json($order);
    }

    public function customerPoints(Customer $customer)
    {
        abort_if($customer->business_id !== auth()->user()->business_id, 403);

        $settings   = auth()->user()->business->settings ?? [];
        $pointValue = (float) ($settings['point_value_rupiah'] ?? 1);

        return response()->json([
            'loyalty_points' => $customer->loyalty_points,
            'point_value'    => $pointValue,
            'max_discount'   => $customer->loyalty_points * $pointValue,
        ]);
    }

    private function sendWhatsAppReceipt(Order $order): void
    {
        $settings = $order->business->settings ?? [];
        if (empty($settings['enable_wa_receipt'])) return;

        $phone = null;
        if ($order->customer_id) {
            $order->loadMissing('customer');
            $phone = $order->customer?->phone ?? $order->customer?->wa_number;
        }
        if (!$phone && !empty($settings['wa_default_phone'])) {
            $phone = $settings['wa_default_phone'];
        }
        if (!$phone) return;

        $order->loadMissing(['items.addons', 'business']);
        $wa = WhatsAppService::forBusiness($order->business);
        $wa?->sendReceipt($order, $phone);
    }
}
