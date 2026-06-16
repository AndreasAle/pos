<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::forBusiness(auth()->user()->business_id)
            ->with('outlet')
            ->latest()
            ->paginate(20);

        return view('promotions.index', compact('promotions'));
    }

    public function create()
    {
        $outlets = Outlet::forBusiness(auth()->user()->business_id)->where('is_active', true)->get();
        return view('promotions.create', compact('outlets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:percent,nominal',
            'value'     => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        Promotion::create([
            'business_id' => auth()->user()->business_id,
        ] + $request->only('name', 'code', 'type', 'value', 'min_order', 'outlet_id', 'starts_at', 'ends_at', 'is_active'));

        return redirect()->route('promotions.index')
            ->with('success', 'Promo berhasil ditambahkan.');
    }

    public function edit(Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);
        $outlets = Outlet::forBusiness(auth()->user()->business_id)->where('is_active', true)->get();
        return view('promotions.edit', compact('promotion', 'outlets'));
    }

    public function update(Request $request, Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'type'  => 'required|in:percent,nominal',
            'value' => 'required|numeric|min:0',
        ]);

        $promotion->update($request->only('name', 'code', 'type', 'value', 'min_order', 'outlet_id', 'starts_at', 'ends_at', 'is_active'));

        return redirect()->route('promotions.index')
            ->with('success', 'Promo berhasil diperbarui.');
    }

    public function destroy(Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);
        $promotion->delete();
        return redirect()->route('promotions.index')
            ->with('success', 'Promo berhasil dihapus.');
    }

    public function toggle(Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);
        $promotion->update(['is_active' => !$promotion->is_active]);
        return back()->with('success', 'Status promo berhasil diubah.');
    }
}
