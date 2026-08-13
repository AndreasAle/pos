<?php

namespace App\Services;

use App\Exceptions\PosTransactionException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\User;

class InventoryService
{
    /**
     * @throws PosTransactionException when stock is short and negatives are disallowed.
     */
    public function deductFromRecipe(Recipe $recipe, float $qty, Order $order, User $user, bool $allowNegative = false): void
    {
        foreach ($recipe->items as $item) {
            $deduct     = $item->qty * $qty;
            $ingredient = $item->ingredient;
            $before     = (float) $ingredient->current_stock;

            if (!$allowNegative && $before < $deduct) {
                throw new PosTransactionException(sprintf(
                    'Stok %s tidak cukup. Butuh %s %s, tersedia %s %s.',
                    $ingredient->name,
                    rtrim(rtrim(number_format($deduct, 3, ',', '.'), '0'), ','),
                    $ingredient->unit,
                    rtrim(rtrim(number_format($before, 3, ',', '.'), '0'), ','),
                    $ingredient->unit
                ));
            }

            $after = $before - $deduct;

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

    /**
     * Give back exactly what this order took.
     *
     * Driven by the recorded 'sale' movements rather than by recomputing the
     * recipes: recipes can be edited after the sale, and a deduction that never
     * happened must never be credited back. Bundles need no special case here —
     * their component deductions were already written as movements.
     */
    public function restoreFromOrder(Order $order, User $user): void
    {
        // Never credit the same order twice.
        $alreadyReturned = StockMovement::where('order_id', $order->id)
            ->where('type', 'return')
            ->exists();

        if ($alreadyReturned) {
            return;
        }

        $soldPerIngredient = StockMovement::where('order_id', $order->id)
            ->where('type', 'sale')
            ->selectRaw('ingredient_id, SUM(qty) as total_qty')
            ->groupBy('ingredient_id')
            ->pluck('total_qty', 'ingredient_id');

        foreach ($soldPerIngredient as $ingredientId => $totalQty) {
            $restore = abs((float) $totalQty);

            if ($restore <= 0) {
                continue;
            }

            $ingredient = Ingredient::find($ingredientId);

            if (!$ingredient) {
                continue;
            }

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
