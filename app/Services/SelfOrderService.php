<?php

namespace App\Services;

use App\Exceptions\PosTransactionException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SelfOrderService
{
    public function __construct(private InventoryService $inventory) {}

    public function openSession(DiningTable $table, array $guest = []): TableSession
    {
        return DB::transaction(function () use ($table, $guest) {
            $open = TableSession::where('dining_table_id', $table->id)->where('status', 'open')->lockForUpdate()->latest()->first();
            if ($open && (! $open->expires_at || $open->expires_at->isFuture())) {
                return $open;
            }

            $session = TableSession::create([
                'business_id' => $table->business_id,
                'outlet_id' => $table->outlet_id,
                'dining_table_id' => $table->id,
                'guest_token' => Str::random(48),
                'status' => 'open',
                'guest_name' => Str::limit(trim((string) ($guest['name'] ?? '')), 80, ''),
                'guest_phone' => Str::limit(trim((string) ($guest['phone'] ?? '')), 24, ''),
                'opened_at' => now(),
                'expires_at' => now()->addHours(8),
            ]);
            $table->update(['status' => 'occupied']);

            return $session;
        });
    }

    public function place(TableSession $session, array $data): Order
    {
        return DB::transaction(function () use ($session, $data) {
            $session = TableSession::whereKey($session->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
            $settings = $session->diningTable->business->settings ?? [];
            $subtotal = 0;
            $lines = [];

            foreach ($data['items'] as $raw) {
                $product = Product::forBusiness($session->business_id)->active()
                    ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $session->outlet_id))
                    ->with(['variants', 'addons'])->findOrFail($raw['product_id']);
                $qty = (float) $raw['qty'];
                if ($qty <= 0) {
                    throw new PosTransactionException('Jumlah produk harus lebih dari 0.');
                }
                $variant = empty($raw['variant_id']) ? null : $product->variants->where('is_active', true)->firstWhere('id', (int) $raw['variant_id']);
                if (! empty($raw['variant_id']) && ! $variant) {
                    throw new PosTransactionException('Varian produk tidak tersedia.');
                }
                $addons = $product->addons->where('is_active', true)->whereIn('id', collect($raw['addons'] ?? [])->map(fn ($x) => (int) ($x['addon_id'] ?? $x)));
                $price = (float) $product->price + (float) ($variant?->price_adjustment ?? 0);
                $line = ($price + $addons->sum(fn ($a) => (float) $a->price)) * $qty;
                $subtotal += $line;
                $lines[] = compact('product', 'qty', 'variant', 'addons', 'price', 'line') + ['notes' => Str::limit((string) ($raw['notes'] ?? ''), 255, '')];
            }

            $tax = ! empty($settings['enable_tax']) ? round($subtotal * ((float) ($settings['tax_percent'] ?? 0) / 100)) : 0;
            $service = ! empty($settings['enable_service']) ? round($subtotal * ((float) ($settings['service_percent'] ?? 0) / 100)) : 0;
            $payAtCashier = ($data['payment_choice'] ?? 'cashier') === 'cashier';
            $order = Order::create([
                'business_id' => $session->business_id, 'outlet_id' => $session->outlet_id, 'user_id' => null,
                'order_number' => 'SO-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'order_type' => 'dine_in', 'order_source' => 'qr_table', 'table_number' => $session->diningTable->name,
                'dining_table_id' => $session->dining_table_id, 'table_session_id' => $session->id,
                'hold_name' => Str::limit(trim((string) ($data['guest_name'] ?? $session->guest_name ?? '')), 60, ''),
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'service_amount' => $service, 'grand_total' => $subtotal + $tax + $service,
                'paid_amount' => 0, 'change_amount' => 0, 'payment_method' => $payAtCashier ? 'cash' : 'qris',
                'payment_status' => 'pending', 'status' => 'pending', 'acceptance_status' => 'pending', 'kitchen_status' => 'pending',
                'expires_at' => now()->addMinutes(20), 'notes' => Str::limit((string) ($data['notes'] ?? ''), 1000, ''), 'ordered_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = OrderItem::create([
                    'order_id' => $order->id, 'product_id' => $line['product']->id, 'product_variant_id' => $line['variant']?->id,
                    'product_name' => $line['product']->name, 'variant_name' => $line['variant']?->name,
                    'price' => $line['price'], 'cost_price' => $line['product']->cost_price, 'qty' => $line['qty'],
                    'subtotal' => $line['line'], 'notes' => $line['notes'], 'kitchen_status' => 'pending',
                ]);
                foreach ($line['addons'] as $addon) {
                    OrderItemAddon::create(['order_item_id' => $item->id, 'product_addon_id' => $addon->id, 'addon_name' => $addon->name, 'price' => $addon->price]);
                }
            }

            return $order->load('items.addons');
        });
    }

    public function accept(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->acceptance_status !== 'pending') {
                return $order;
            }
            $allowNegative = ! empty($order->business->settings['allow_negative_stock']);
            foreach ($order->items()->with('product.recipe.items.ingredient')->get() as $item) {
                if ($item->product?->is_stock_tracked && $item->product->recipe) {
                    $this->inventory->deductFromRecipe($item->product->recipe, (float) $item->qty, $order, $user, $allowNegative);
                }
            }
            $order->update(['acceptance_status' => 'accepted', 'accepted_by' => $user->id, 'accepted_at' => now(), 'user_id' => $user->id]);

            return $order->refresh();
        });
    }
}
