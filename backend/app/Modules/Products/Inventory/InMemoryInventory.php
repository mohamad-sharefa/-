<?php

namespace App\Modules\Products\Inventory;

/**
 * Inventory kept in memory, used by the test suite (INVENTORY_DRIVER=memory) so tests
 * don't need the gRPC service. Behaves like the real service, and can pretend to be down.
 */
class InMemoryInventory implements InventoryService
{
    /** @var array<int, int> product id => quantity */
    private array $stock = [];

    /** @var array<string, array{released: bool, quantities: array<int, int>}> */
    private array $reservations = [];

    public bool $unavailable = false;

    /** Simulates a timeout that hits after the reservation was already made on the other side */
    public bool $timeOutAfterReserving = false;

    public function stockOf(array $productIds): array
    {
        $this->ensureAvailable();

        return array_combine($productIds, array_map(fn ($id) => $this->stock[$id] ?? 0, $productIds));
    }

    public function reserve(string $reservationId, array $quantities): void
    {
        $this->ensureAvailable();

        if (isset($this->reservations[$reservationId])) {
            return;
        }

        $short = [];
        foreach ($quantities as $productId => $quantity) {
            if (($this->stock[$productId] ?? 0) < $quantity) {
                $short[$productId] = $this->stock[$productId] ?? 0;
            }
        }
        if ($short !== []) {
            throw new InsufficientStockException($short);
        }

        foreach ($quantities as $productId => $quantity) {
            $this->stock[$productId] -= $quantity;
        }
        $this->reservations[$reservationId] = ['released' => false, 'quantities' => $quantities];

        if ($this->timeOutAfterReserving) {
            throw new InventoryUnavailableException('timeout');
        }
    }

    public function release(string $reservationId): void
    {
        $this->ensureAvailable();

        $reservation = $this->reservations[$reservationId] ?? null;
        if ($reservation === null || $reservation['released']) {
            return;
        }

        foreach ($reservation['quantities'] as $productId => $quantity) {
            $this->stock[$productId] += $quantity;
        }
        $this->reservations[$reservationId]['released'] = true;
    }

    public function setStock(int $productId, int $quantity): void
    {
        $this->ensureAvailable();

        $this->stock[$productId] = $quantity;
    }

    private function ensureAvailable(): void
    {
        if ($this->unavailable) {
            throw new InventoryUnavailableException('unavailable');
        }
    }
}
