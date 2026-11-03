<?php

namespace App\Modules\Products\Inventory;

/**
 * Everything the rest of the app needs from stock management.
 *
 * Today it is backed by the products table (DatabaseInventory). Requirement 2 moves
 * inventory into its own service reached over gRPC: only a new implementation of this
 * interface is needed then, the Orders module stays as it is.
 */
interface InventoryService
{
    /**
     * Take the quantities out of stock, all or nothing.
     *
     * @param  array<int, int>  $quantities  product id => quantity
     *
     * @throws InsufficientStockException when any product has less than requested
     */
    public function reserve(array $quantities): void;

    /**
     * Put previously reserved quantities back into stock.
     *
     * @param  array<int, int>  $quantities  product id => quantity
     */
    public function release(array $quantities): void;
}
