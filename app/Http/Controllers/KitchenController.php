<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $orders = Order::forBusiness($user->business_id)
            ->where('status', 'paid')
            ->whereNotIn('kitchen_status', ['completed'])
            ->with('items', 'outlet')
            ->when($user->outlet_id, fn($q) => $q->where('outlet_id', $user->outlet_id))
            ->latest()
            ->get();

        return view('kitchen.index', compact('orders'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_if($order->business_id !== auth()->user()->business_id, 403);
        $request->validate(['status' => 'required|in:preparing,ready,completed']);
        $order->update(['kitchen_status' => $request->status]);
        return response()->json(['success' => true]);
    }
}
