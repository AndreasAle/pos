<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\ProductBundle;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function deductFromRecipe(Recipe $recipe, float $qty, Order $order, User $user, bool $allowNegative = false): void
    {
        foreach ($recipe->items as $item) {
            $deduct     = $item->qty * $qty;
            $ingredient = $item->ingredient;

            if (!$allowNegative && $ingredient->current_stock < $deduct) {
                continue;
            }

            $before = (float) $ingredient->current_stock;
            $after  = max(0, $before - $deduct);
            if ($allowNegative) {
                $after = $before - $deduct;
            }

            $ingredient->update(['current_stock' => $after]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'business_id'   => $ingredient->business_id,
                'outlet_id'     => $ingredient->outlet_id,
                'order_id'      => $order->id,
                'user_id'       => $user->id,
                'type'          => 'sale',
                'qty'           => -$deduct,
                'stock_before'  => $before,
                'stock_after'   => $after,
                'unit_cost'     => $ingredient->average_cost,
                'reference'     => $order->order_number,
                'notes'         => 'Auto deduct from order ' . $order->order_number,
            ]);
        }
    }

    public function restoreFromOrder(Order $order, User $user): void
    {
        $order->loadMissing('items.product.recipe.items.ingredient');

        foreach ($order->items as $item) {
            $qty = (float) $item->qty;

            if ($item->product_id && $item->product) {
                $product = $item->product;
                if ($product->is_stock_tracked && $product->recipe) {
                    $this->restoreFromRecipe($product->recipe, $qty, $order, $user);
                }
            } elseif ($item->product_bundle_id) {
                $bundle = ProductBundle::with('items.product.recipe.items.ingredient')
                    ->find($item->product_bundle_id);
                if ($bundle) {
                    foreach ($bundle->items as $bundleItem) {
                        $prod = $bundleItem->product;
                        if ($prod && $prod->is_stock_tracked && $prod->recipe) {
                            $this->restoreFromRecipe(
                                $prod->recipe,
                                (float) $bundleItem->qty * $qty,
                                $order,
                                $user
                            );
                        }
                    }
                }
            }
        }
    }

    private function restoreFromRecipe(Recipe $recipe, float $qty, Order $order, User $user): void
    {
        foreach ($recipe->items as $item) {
            $restore    = (float) $item->qty * $qty;
            $ingredient = $item->ingredient;

            $before = (float) $ingredient->current_stock;
            $after  = $before + $restore;

            $ingredient->update(['current_stock' => $after]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'business_id'   => $ingredient->business_id,
                'outlet_id'     => $ingredient->outlet_id,
                'order_id'      => $order->id,
                'user_id'       => $user->id,
                'type'          => 'return',
                'qty'           => $restore,
                'stock_before'  => $before,
                'stock_after'   => $after,
                'unit_cost'     => $ingredient->average_cost,
                'reference'     => $order->order_number,
                'notes'         => 'Stok kembali dari pembatalan order ' . $order->order_number,
            ]);
        }
    }

    public function addStock(Ingredient $ingredient, float $qty, float $unitCost, User $user, string $notes = ''): void
    {
        $before = (float) $ingredient->current_stock;
        $after  = $before + $qty;

        // Update average cost (weighted average)
        if ($qty > 0 && $unitCost > 0) {
            $totalValue    = ($before * $ingredient->average_cost) + ($qty * $unitCost);
            $totalQty      = $after;
            $newAvgCost    = $totalQty > 0 ? $totalValue / $totalQty : $unitCost;
            $ingredient->update(['current_stock' => $after, 'average_cost' => $newAvgCost]);
        } else {
            $ingredient->update(['current_stock' => $after]);
        }

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'business_id'   => $ingredient->business_id,
            'outlet_id'     => $ingredient->outlet_id,
            'user_id'       => $user->id,
            'type'          => 'in',
            'qty'           => $qty,
            'stock_before'  => $before,
            'stock_after'   => $after,
            'unit_cost'     => $unitCost,
            'notes'         => $notes,
        ]);
    }

    public function adjustStock(Ingredient $ingredient, float $newStock, User $user, string $notes = ''): void
    {
        $before = (float) $ingredient->current_stock;
        $diff   = $newStock - $before;

        $ingredient->update(['current_stock' => $newStock]);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'business_id'   => $ingredient->business_id,
            'outlet_id'     => $ingredient->outlet_id,
            'user_id'       => $user->id,
            'type'          => 'adjustment',
            'qty'           => $diff,
            'stock_before'  => $before,
            'stock_after'   => $newStock,
            'unit_cost'     => $ingredient->average_cost,
            'notes'         => $notes ?: 'Penyesuaian stok manual',
        ]);
    }
}
