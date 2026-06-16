<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index()
    {
        $products = Product::forBusiness(auth()->user()->business_id)
            ->with('recipe.items.ingredient')
            ->where('is_stock_tracked', true)
            ->paginate(20);

        return view('inventory.recipes.index', compact('products'));
    }

    public function edit(Product $product)
    {
        abort_if($product->business_id !== auth()->user()->business_id, 403);
        $product->load('recipe.items.ingredient');
        $ingredients = Ingredient::forBusiness(auth()->user()->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('inventory.recipes.edit', compact('product', 'ingredients'));
    }

    public function update(Request $request, Product $product)
    {
        abort_if($product->business_id !== auth()->user()->business_id, 403);

        $request->validate([
            'items'                    => 'nullable|array',
            'items.*.ingredient_id'    => 'required|exists:ingredients,id',
            'items.*.qty'              => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($request, $product) {
            $recipe = $product->recipe
                ?? Recipe::create(['product_id' => $product->id, 'business_id' => $product->business_id]);

            $recipe->items()->delete();

            foreach ($request->items ?? [] as $item) {
                RecipeItem::create([
                    'recipe_id'     => $recipe->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'qty'           => $item['qty'],
                ]);
            }
        });

        return redirect()->route('products.show', $product)
            ->with('success', 'Resep berhasil disimpan.');
    }
}
