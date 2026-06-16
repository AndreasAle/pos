<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;

class PosOrderService
{
    public function __construct(protected InventoryService $inventory) {}

    public function createOrder($user, array $data, bool $paymentPending = false): Order
    {
        return DB::transaction(function () use ($user, $data, $paymentPending) {
            $business = $user->business;
            $settings = $business->settings ?? [];
            $shift    = $user->activeShift()->first();
            $allowNeg = !empty($settings['allow_negative_stock']);

            // ── Build normalised item list ────────────────────────────────────
            $subtotal = 0;
            $items    = [];

            foreach ($data['items'] as $raw) {
                if (!empty($raw['bundle_id'])) {
                    $bundle    = ProductBundle::forBusiness($business->id)
                        ->with('items.product.recipe.items.ingredient')
                        ->findOrFail($raw['bundle_id']);
                    $qty       = (float) ($raw['qty'] ?? 1);
                    $price     = (float) $bundle->price;
                    $lineTotal = $price * $qty;
                    $subtotal += $lineTotal;
                    $items[] = [
                        'type'         => 'bundle',
                        'bundle'       => $bundle,
                        'product'      => null,
                        'qty'          => $qty,
                        'price'        => $price,
                        'cost_price'   => $bundle->cost_price ?? 0,
                        'subtotal'     => $lineTotal,
                        'addons'       => [],
                        'notes'        => $raw['notes'] ?? null,
                        'variant_id'   => null,
                        'variant_name' => null,
                    ];
                } else {
                    $product    = Product::forBusiness($business->id)
                        ->with('recipe.items.ingredient')
                        ->findOrFail($raw['product_id']);
                    $qty        = (float) $raw['qty'];
                    $price      = (float) $raw['price'];
                    $addonTotal = 0;
                    $addons     = [];

                    foreach ($raw['addons'] ?? [] as $addonData) {
                        $addonTotal += (float) $addonData['price'];
                        $addons[]    = $addonData;
                    }

                    $lineTotal  = ($price + $addonTotal) * $qty;
                    $subtotal  += $lineTotal;

                    $items[] = [
                        'type'         => 'product',
                        'bundle'       => null,
                        'product'      => $product,
                        'qty'          => $qty,
                        'price'        => $price,
                        'cost_price'   => $product->cost_price,
                        'subtotal'     => $lineTotal,
                        'addons'       => $addons,
                        'notes'        => $raw['notes'] ?? null,
                        'variant_id'   => $raw['variant_id'] ?? null,
                        'variant_name' => $raw['variant_name'] ?? null,
                    ];
                }
            }

            // ── Discount ─────────────────────────────────────────────────────
            $discountAmount = 0;
            $promotionId    = null;

            if (!empty($data['promotion_id'])) {
                $promo = Promotion::forBusiness($business->id)->active()->find($data['promotion_id']);
                if ($promo) {
                    $discountAmount = $promo->calculateDiscount($subtotal);
                    $promotionId    = $promo->id;
                }
            }
            if (!empty($data['manual_discount'])) {
                $discountAmount = max($discountAmount, (float) $data['manual_discount']);
            }

            $redeemPoints   = (int) ($data['redeem_points'] ?? 0);
            $pointsDiscount = 0;
            if ($redeemPoints > 0 && !empty($data['customer_id'])) {
                $pointValue     = (float) ($settings['point_value_rupiah'] ?? 1);
                $pointsDiscount = $redeemPoints * $pointValue;
            }

            $discountAmount = min($subtotal, $discountAmount + $pointsDiscount);
            $afterDiscount  = max(0, $subtotal - $discountAmount);

            // ── Tax & Service ─────────────────────────────────────────────────
            $taxAmount     = 0;
            $serviceAmount = 0;
            if (!empty($settings['enable_tax']) && !empty($settings['tax_percent'])) {
                $taxAmount = round($afterDiscount * ($settings['tax_percent'] / 100));
            }
            if (!empty($settings['enable_service']) && !empty($settings['service_percent'])) {
                $serviceAmount = round($afterDiscount * ($settings['service_percent'] / 100));
            }

            // ── Delivery fee ─────────────────────────────────────────────────
            $deliveryFee = (float) ($data['delivery_fee'] ?? 0);

            $grandTotal = $afterDiscount + $taxAmount + $serviceAmount + $deliveryFee;

            // ── Split billing ─────────────────────────────────────────────────
            $splitPayments  = $data['split_payments'] ?? [];
            $isSplit        = !empty($splitPayments) && count($splitPayments) > 1;
            $paidAmount     = $isSplit
                ? collect($splitPayments)->sum('amount')
                : (float) $data['paid_amount'];
            $changeAmount   = max(0, $paidAmount - $grandTotal);
            $primaryMethod  = $isSplit
                ? ($splitPayments[0]['method'] ?? 'cash')
                : $data['payment_method'];

            // ── Create Order ─────────────────────────────────────────────────
            $order = Order::create([
                'business_id'      => $business->id,
                'outlet_id'        => $shift?->outlet_id ?? $user->outlet_id,
                'user_id'          => $user->id,
                'cashier_shift_id' => $shift?->id,
                'customer_id'      => $data['customer_id'] ?? null,
                'promotion_id'     => $promotionId,
                'order_number'     => $this->generateOrderNumber($business->id),
                'order_type'       => $data['order_type'] ?? 'dine_in',
                'delivery_platform'=> $data['delivery_platform'] ?? null,
                'delivery_fee'     => $deliveryFee,
                'customer_address' => $data['customer_address'] ?? null,
                'delivery_notes'   => $data['delivery_notes'] ?? null,
                'platform_order_number' => $data['platform_order_number'] ?? null,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discountAmount,
                'tax_amount'       => $taxAmount,
                'service_amount'   => $serviceAmount,
                'grand_total'      => $grandTotal,
                'paid_amount'      => $paidAmount,
                'change_amount'    => $changeAmount,
                'payment_method'   => $primaryMethod,
                'payment_status'   => $paymentPending ? 'pending' : 'paid',
                'status'           => $paymentPending ? 'pending' : 'paid',
                'kitchen_status'   => 'pending',
                'is_split_payment' => $isSplit,
                'notes'            => $data['notes'] ?? null,
                'ordered_at'       => now(),
            ]);

            // ── Save split payment records ────────────────────────────────────
            if ($isSplit) {
                foreach ($splitPayments as $pay) {
                    OrderPayment::create([
                        'order_id'       => $order->id,
                        'payment_method' => $pay['method'],
                        'amount'         => (float) $pay['amount'],
                        'reference'      => $pay['reference'] ?? null,
                        'notes'          => $pay['notes'] ?? null,
                    ]);
                }
            }

            // ── Persist order items ───────────────────────────────────────────
            foreach ($items as $item) {
                if ($item['type'] === 'bundle') {
                    $orderItem = OrderItem::create([
                        'order_id'          => $order->id,
                        'product_id'        => null,
                        'product_bundle_id' => $item['bundle']->id,
                        'product_name'      => $item['bundle']->name,
                        'price'             => $item['price'],
                        'cost_price'        => $item['cost_price'],
                        'qty'               => $item['qty'],
                        'subtotal'          => $item['subtotal'],
                        'notes'             => $item['notes'],
                        'kitchen_status'    => 'pending',
                    ]);

                    foreach ($item['bundle']->items as $bundleItem) {
                        $prod = $bundleItem->product;
                        if ($prod && $prod->is_stock_tracked && $prod->recipe) {
                            $this->inventory->deductFromRecipe(
                                $prod->recipe,
                                (float) $bundleItem->qty * $item['qty'],
                                $order,
                                $user,
                                $allowNeg
                            );
                        }
                    }
                } else {
                    $orderItem = OrderItem::create([
                        'order_id'           => $order->id,
                        'product_id'         => $item['product']->id,
                        'product_variant_id' => $item['variant_id'],
                        'product_name'       => $item['product']->name,
                        'variant_name'       => $item['variant_name'],
                        'price'              => $item['price'],
                        'cost_price'         => $item['cost_price'],
                        'qty'                => $item['qty'],
                        'subtotal'           => $item['subtotal'],
                        'notes'              => $item['notes'],
                        'kitchen_status'     => 'pending',
                    ]);

                    foreach ($item['addons'] as $addonData) {
                        OrderItemAddon::create([
                            'order_item_id'    => $orderItem->id,
                            'product_addon_id' => $addonData['addon_id'] ?? null,
                            'addon_name'       => $addonData['name'],
                            'price'            => $addonData['price'],
                        ]);
                    }

                    if ($item['product']->is_stock_tracked && $item['product']->recipe) {
                        $this->inventory->deductFromRecipe(
                            $item['product']->recipe,
                            $item['qty'],
                            $order,
                            $user,
                            $allowNeg
                        );
                    }
                }
            }

            // ── Customer stats + loyalty (skip if payment pending) ───────────
            if (!$paymentPending && $order->customer_id) {
                $customer = Customer::find($order->customer_id);
                if ($customer) {
                    $customer->increment('total_transactions');
                    $customer->increment('total_spending', $grandTotal);

                    if ($redeemPoints > 0 && $customer->loyalty_points >= $redeemPoints) {
                        $customer->decrement('loyalty_points', $redeemPoints);
                        $customer->points()->create([
                            'order_id'    => $order->id,
                            'type'        => 'redeem',
                            'points'      => -$redeemPoints,
                            'description' => 'Penukaran poin di transaksi ' . $order->order_number,
                        ]);
                    }

                    $ppr = (float) ($settings['points_per_rupiah'] ?? 0);
                    if ($ppr > 0) {
                        $earned = (int) floor($grandTotal * $ppr);
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

            return $order;
        });
    }

    public function holdOrder($user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $business = $user->business;
            $subtotal = 0;

            $order = Order::create([
                'business_id'     => $business->id,
                'outlet_id'       => $user->outlet_id,
                'user_id'         => $user->id,
                'cashier_shift_id'=> $user->activeShift()->value('id'),
                'order_number'    => $this->generateOrderNumber($business->id),
                'subtotal'        => 0,
                'grand_total'     => 0,
                'paid_amount'     => 0,
                'change_amount'   => 0,
                'payment_method'  => 'cash',
                'payment_status'  => 'unpaid',
                'status'          => 'draft',
                'kitchen_status'  => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::forBusiness($business->id)->findOrFail($item['product_id']);
                $qty     = (float) $item['qty'];
                $price   = (float) ($item['price'] ?? $product->price);
                $sub     = $price * $qty;
                $subtotal += $sub;

                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'price'         => $price,
                    'cost_price'    => $product->cost_price,
                    'qty'           => $qty,
                    'subtotal'      => $sub,
                    'kitchen_status'=> 'pending',
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'grand_total' => $subtotal]);

            return $order;
        });
    }

    private function generateOrderNumber(int $businessId): string
    {
        $prefix = 'ORD';
        $date   = now()->format('ymd');
        $like   = $prefix . '-' . $date . '-%';

        // Use MAX of existing sequence to avoid duplicate when orders are deleted/voided
        $maxSeq = Order::where('business_id', $businessId)
            ->where('order_number', 'like', $like)
            ->max(DB::raw("CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED)"));

        $next = ($maxSeq ?? 0) + 1;

        // Retry loop in case of race condition
        while (Order::where('order_number', $prefix . '-' . $date . '-' . str_pad($next, 4, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return $prefix . '-' . $date . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
