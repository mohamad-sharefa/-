<?php

namespace App\Modules\Products\Inventory;

use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;

class DatabaseInventory implements InventoryService
{
    public function reserve(array $quantities): void
    {
        DB::transaction(function () use ($quantities) {
            // Lock the rows (in id order, to avoid deadlocks) so two orders can't both take the last piece
            $products = Product::whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'stock']);

            $short = [];
            foreach ($products as $product) {
                if ($product->stock < $quantities[$product->id]) {
                    $short[$product->id] = $product->stock;
                }
            }
            if ($short !== []) {
                throw new InsufficientStockException($short);
            }

            foreach ($products as $product) {
                $product->decrement('stock', $quantities[$product->id]);
            }
        });
    }

    public function release(array $quantities): void
    {
        DB::transaction(function () use ($quantities) {
            foreach ($quantities as $productId => $quantity) {
                Product::whereKey($productId)->increment('stock', $quantity);
            }
        });
    }
}
