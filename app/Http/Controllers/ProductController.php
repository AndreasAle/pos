<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $businessId = auth()->user()->business_id;

        $query = Product::forBusiness($businessId)
            ->with('category')
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%')
            )
            ->when($request->category_id, fn($q) =>
                $q->where('product_category_id', $request->category_id)
            )
            ->when($request->status !== null, fn($q) =>
                $q->where('is_active', $request->status)
            )
            ->orderBy('sort_order')
            ->orderBy('name');

        $products   = $query->paginate(24)->withQueryString();
        $categories = ProductCategory::forBusiness($businessId)->active()->orderBy('sort_order')->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $businessId = auth()->user()->business_id;
        $categories = ProductCategory::forBusiness($businessId)->active()->orderBy('sort_order')->get();
        $outlets    = Outlet::forBusiness($businessId)->where('is_active', true)->get();

        return view('products.create', compact('categories', 'outlets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'outlet_id'           => 'nullable|exists:outlets,id',
            'sku'                 => 'nullable|string|max:100',
            'description'         => 'nullable|string|max:1000',
            'price'               => 'required|numeric|min:0',
            'cost_price'          => 'nullable|numeric|min:0',
            'is_active'           => 'boolean',
            'is_stock_tracked'    => 'boolean',
            'image'               => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $businessId = auth()->user()->business_id;
        $imagePath  = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $count = Product::forBusiness($businessId)->count();

        Product::create([
            'business_id'         => $businessId,
            'outlet_id'           => $request->outlet_id,
            'product_category_id' => $request->product_category_id,
            'name'                => $request->name,
            'sku'                 => $request->sku,
            'description'         => $request->description,
            'image'               => $imagePath,
            'price'               => $request->price,
            'cost_price'          => $request->cost_price ?? 0,
            'is_active'           => $request->boolean('is_active', true),
            'is_stock_tracked'    => $request->boolean('is_stock_tracked'),
            'sort_order'          => $count + 1,
        ]);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function show(Product $product)
    {
        $this->authorizeBusiness($product);
        $product->load('category', 'variants', 'addons', 'recipe.items.ingredient');

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $this->authorizeBusiness($product);

        $businessId = auth()->user()->business_id;
        $categories = ProductCategory::forBusiness($businessId)->active()->orderBy('sort_order')->get();
        $outlets    = Outlet::forBusiness($businessId)->where('is_active', true)->get();
        $product->load('variants', 'addons');

        return view('products.edit', compact('product', 'categories', 'outlets'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeBusiness($product);

        $request->validate([
            'name'                => 'required|string|max:255',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'outlet_id'           => 'nullable|exists:outlets,id',
            'sku'                 => 'nullable|string|max:100',
            'description'         => 'nullable|string|max:1000',
            'price'               => 'required|numeric|min:0',
            'cost_price'          => 'nullable|numeric|min:0',
            'is_active'           => 'boolean',
            'is_stock_tracked'    => 'boolean',
            'image'               => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $data = $request->only(
            'name', 'product_category_id', 'outlet_id', 'sku',
            'description', 'price', 'cost_price'
        );
        $data['is_active']        = $request->boolean('is_active');
        $data['is_stock_tracked'] = $request->boolean('is_stock_tracked');
        $data['cost_price']       = $request->cost_price ?? 0;

        if ($request->hasFile('image')) {
            if ($product->image) {
                \Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeBusiness($product);

        if ($product->image) {
            \Storage::disk('public')->delete($product->image);
        }

        $product->delete();
        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    public function toggle(Product $product)
    {
        $this->authorizeBusiness($product);
        $product->update(['is_active' => !$product->is_active]);

        return back()->with('success', 'Status produk berhasil diubah.');
    }

    // ── Variants ──────────────────────────────────────────────────────────────

    public function storeVariant(Request $request, Product $product)
    {
        $this->authorizeBusiness($product);

        $request->validate([
            'name'             => 'required|string|max:255',
            'price_adjustment' => 'required|numeric',
        ]);

        $count = $product->variants()->count();
        $product->variants()->create([
            'name'             => $request->name,
            'price_adjustment' => $request->price_adjustment,
            'is_active'        => true,
            'sort_order'       => $count + 1,
        ]);

        return back()->with('success', 'Varian berhasil ditambahkan.');
    }

    public function destroyVariant(Product $product, ProductVariant $variant)
    {
        $this->authorizeBusiness($product);
        abort_if($variant->product_id !== $product->id, 403);

        $variant->delete();
        return back()->with('success', 'Varian berhasil dihapus.');
    }

    // ── Addons ────────────────────────────────────────────────────────────────

    public function storeAddon(Request $request, Product $product)
    {
        $this->authorizeBusiness($product);

        $request->validate([
            'name'  => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $count = $product->addons()->count();
        $product->addons()->create([
            'name'       => $request->name,
            'price'      => $request->price,
            'is_active'  => true,
            'sort_order' => $count + 1,
        ]);

        return back()->with('success', 'Add-on berhasil ditambahkan.');
    }

    public function destroyAddon(Product $product, ProductAddon $addon)
    {
        $this->authorizeBusiness($product);
        abort_if($addon->product_id !== $product->id, 403);

        $addon->delete();
        return back()->with('success', 'Add-on berhasil dihapus.');
    }

    private function authorizeBusiness(Product $product): void
    {
        abort_if($product->business_id !== auth()->user()->business_id, 403);
    }
}
