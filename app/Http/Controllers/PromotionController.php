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
        $outlets    = Outlet::forBusiness(auth()->user()->business_id)->where('is_active', true)->get();
        $categories = $this->categories();
        return view('promotions.create', compact('outlets', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate($this->rules() + [
            'min_order' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        Promotion::create([
            'business_id' => auth()->user()->business_id,
        ] + $this->fields($request));

        return redirect()->route('promotions.index')
            ->with('success', 'Promo berhasil ditambahkan.');
    }

    public function edit(Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);
        $outlets    = Outlet::forBusiness(auth()->user()->business_id)->where('is_active', true)->get();
        $categories = $this->categories();
        return view('promotions.edit', compact('promotion', 'outlets', 'categories'));
    }

    public function update(Request $request, Promotion $promotion)
    {
        abort_if($promotion->business_id !== auth()->user()->business_id, 403);

        $request->validate($this->rules());

        $promotion->update($this->fields($request));

        return redirect()->route('promotions.index')
            ->with('success', 'Promo berhasil diperbarui.');
    }

    private function rules(): array
    {
        $businessId = auth()->user()->business_id;

        return [
            'name'                => 'required|string|max:255',
            'type'                => 'required|in:percent,nominal,buy_get',
            'value'               => 'required_unless:type,buy_get|nullable|numeric|min:0',
            'buy_qty'             => 'required_if:type,buy_get|nullable|integer|min:1|max:99',
            'get_qty'             => 'required_if:type,buy_get|nullable|integer|min:1|max:99',
            'product_category_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('product_categories', 'id')->where('business_id', $businessId),
            ],
        ];
    }

    private function fields(Request $request): array
    {
        $data = $request->only('name', 'code', 'type', 'value', 'min_order', 'outlet_id', 'starts_at', 'ends_at', 'is_active');

        if ($request->type === 'buy_get') {
            $data['value']               = 0;
            $data['buy_qty']             = (int) $request->buy_qty;
            $data['get_qty']             = (int) $request->get_qty;
            $data['product_category_id'] = $request->product_category_id ?: null;
        } else {
            $data['product_category_id'] = null;
        }

        return $data;
    }

    private function categories()
    {
        return \App\Models\ProductCategory::where('business_id', auth()->user()->business_id)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
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
