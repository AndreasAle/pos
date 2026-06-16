<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::forBusiness(auth()->user()->business_id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        $count = ProductCategory::forBusiness(auth()->user()->business_id)->count();

        ProductCategory::create([
            'business_id' => auth()->user()->business_id,
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . Str::random(4),
            'color'       => $request->color ?? '#10b981',
            'sort_order'  => $count + 1,
            'is_active'   => true,
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        abort_if($category->business_id !== auth()->user()->business_id, 403);

        $request->validate([
            'name'       => 'required|string|max:255',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $category->update($request->only('name', 'color', 'sort_order', 'is_active'));

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ProductCategory $category)
    {
        abort_if($category->business_id !== auth()->user()->business_id, 403);

        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih memiliki produk.');
        }

        $category->delete();
        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
