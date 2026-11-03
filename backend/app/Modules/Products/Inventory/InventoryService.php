<?php

namespace App\Modules\Products\Inventory;

/**
 * Everything the application needs from stock management. Stock lives in the separate
 * inventory service (GrpcInventory); tests use InMemoryInventory.
 */
interface InventoryService
{
    /**
     * How many pieces of each product are left.
     *
     * @param  list<int>  $productIds
     * @return array<int, int> product id => quantity
     *
     * @throws InventoryUnavailableException
     */
    public function stockOf(array $productIds): array;

    /**
     * Take the quantities out of stock, all or nothing.
     *
     * @param  string  $reservationId  a UUID chosen by the caller, used later to release it
     * @param  array<int, int>  $quantities  product id => quantity
     *
     * @throws InsufficientStockException when any product has less than requested
     * @throws InventoryUnavailableException
     */
    public function reserve(string $reservationId, array $quantities): void;

    /**
     * Give back everything a reservation took. Safe to call more than once.
     *
     * @throws InventoryUnavailableException
     */
    public function release(string $reservationId): void;

    /**
     * @throws InventoryUnavailableException
     */
    public function setStock(int $productId, int $quantity): void;
}
