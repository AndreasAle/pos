<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request)
    {
        $user   = auth()->user();
        $orders = Order::forBusiness($user->business_id)
            ->with(['outlet', 'user'])
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->order_type, fn($q) => $q->where('order_type', $request->order_type))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $order->load('items.addons', 'items.bundle.items.product', 'outlet', 'user', 'customer', 'shift', 'payments');
        return view('orders.show', compact('order'));
    }

    public function void(Request $request, Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        abort_if($order->status !== 'paid', 422);

        $request->validate(['reason' => 'required|string|max:255']);

        DB::transaction(function () use ($order, $request) {
            $user = auth()->user();

            $order->update([
                'status'        => 'cancelled',
                'cancel_reason' => $request->reason,
            ]);

            $this->inventory->restoreFromOrder($order, $user);
            $this->reverseCustomerLoyalty($order, 'Poin dibatalkan karena void order', 'Poin dikembalikan karena void order');
        });

        return back()->with('success', 'Order berhasil dibatalkan dan stok dikembalikan.');
    }

    public function refund(Request $request, Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        abort_if($order->status !== 'paid', 422);

        $request->validate(['reason' => 'required|string|max:255']);

        DB::transaction(function () use ($order, $request) {
            $user = auth()->user();

            $order->update([
                'status'         => 'refunded',
                'payment_status' => 'refunded',
                'refund_reason'  => $request->reason,
                'refunded_at'    => now(),
            ]);

            // Restore inventory
            $this->inventory->restoreFromOrder($order, $user);

            // Reverse loyalty points
            $this->reverseCustomerLoyalty($order, 'Poin dibatalkan karena refund order', 'Poin dikembalikan karena refund order');
        });

        return back()->with('success', 'Refund berhasil. Stok dan poin sudah dikembalikan.');
    }

    private function reverseCustomerLoyalty(Order $order, string $deductDesc, string $restoreDesc): void
    {
        if (!$order->customer_id) return;

        $customer = Customer::find($order->customer_id);
        if (!$customer) return;

        $earned = $customer->points()
            ->where('order_id', $order->id)
            ->where('type', 'earn')
            ->sum('points');

        if ($earned > 0) {
            $customer->decrement('loyalty_points', $earned);
            $customer->points()->create([
                'order_id'    => $order->id,
                'type'        => 'adjustment',
                'points'      => -$earned,
                'description' => $deductDesc . ' ' . $order->order_number,
            ]);
        }

        $redeemed = abs($customer->points()
            ->where('order_id', $order->id)
            ->where('type', 'redeem')
            ->sum('points'));

        if ($redeemed > 0) {
            $customer->increment('loyalty_points', $redeemed);
            $customer->points()->create([
                'order_id'    => $order->id,
                'type'        => 'adjustment',
                'points'      => $redeemed,
                'description' => $restoreDesc . ' ' . $order->order_number,
            ]);
        }

        $customer->decrement('total_transactions');
        $customer->decrement('total_spending', $order->grand_total);
    }
}
