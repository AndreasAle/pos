<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBundleController extends Controller
{
    public function index()
    {
        $bundles = ProductBundle::forBusiness(auth()->user()->business_id)
            ->with('items.product')
            ->withCount('items')
            ->latest()
            ->get();

        return view('bundles.index', compact('bundles'));
    }

    public function create()
    {
        $products = Product::forBusiness(auth()->user()->business_id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('bundles.create', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
            'price'             => 'required|numeric|min:0',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|exists:products,id',
            'items.*.qty'       => 'required|numeric|min:0.001',
        ]);

        $business = auth()->user()->business;

        DB::transaction(function () use ($request, $business) {
            $bundle = ProductBundle::create([
                'business_id' => $business->id,
                'name'        => $request->name,
                'description' => $request->description,
                'price'       => $request->price,
                'is_active'   => true,
            ]);

            foreach ($request->items as $item) {
                ProductBundleItem::create([
                    'product_bundle_id' => $bundle->id,
                    'product_id'        => $item['product_id'],
                    'qty'               => $item['qty'],
                ]);
            }
        });

        return redirect()->route('bundles.index')
            ->with('success', 'Paket bundle berhasil dibuat.');
    }

    public function edit(ProductBundle $bundle)
    {
        abort_if($bundle->business_id !== auth()->user()->business_id, 403);

        $bundle->load('items.product');
        $products = Product::forBusiness(auth()->user()->business_id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('bundles.edit', compact('bundle', 'products'));
    }

    public function update(Request $request, ProductBundle $bundle)
    {
        abort_if($bundle->business_id !== auth()->user()->business_id, 403);

        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:500',
            'price'             => 'required|numeric|min:0',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|exists:products,id',
            'items.*.qty'       => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($request, $bundle) {
            $bundle->update([
                'name'        => $request->name,
                'description' => $request->description,
                'price'       => $request->price,
            ]);

            $bundle->items()->delete();

            foreach ($request->items as $item) {
                ProductBundleItem::create([
                    'product_bundle_id' => $bundle->id,
                    'product_id'        => $item['product_id'],
                    'qty'               => $item['qty'],
                ]);
            }
        });

        return redirect()->route('bundles.index')
            ->with('success', 'Bundle berhasil diperbarui.');
    }

    public function destroy(ProductBundle $bundle)
    {
        abort_if($bundle->business_id !== auth()->user()->business_id, 403);
        $bundle->delete();

        return back()->with('success', 'Bundle berhasil dihapus.');
    }

    public function toggle(ProductBundle $bundle)
    {
        abort_if($bundle->business_id !== auth()->user()->business_id, 403);
        $bundle->update(['is_active' => !$bundle->is_active]);

        return back()->with('success', 'Status bundle diperbarui.');
    }
}
