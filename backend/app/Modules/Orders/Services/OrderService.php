<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Inventory\InsufficientStockException;
use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Inventory\InventoryUnavailableException;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * 1. One gRPC call reserves the quantities of every product in the order (all or nothing).
     * 2. The order is saved in our database.
     * 3. If saving fails after the reservation, the reservation is released (compensation),
     *    so stock is never lost to an order that doesn't exist.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     *
     * @throws ValidationException when a product doesn't have enough stock
     * @throws InventoryUnavailableException when the inventory service is down or too slow
     */
    public function place(User $customer, array $lines): Order
    {
        $quantities = collect($lines)->mapWithKeys(fn ($line) => [(int) $line['product_id'] => (int) $line['quantity']])->all();
        $products = Product::whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $reservationId = (string) Str::uuid();

        try {
            $this->inventory->reserve($reservationId, $quantities);
        } catch (InsufficientStockException $e) {
            throw $this->stockError($lines, $e->available);
        } catch (InventoryUnavailableException $e) {
            // On a timeout we don't know whether the service still made the reservation.
            // Releasing an unknown reservation is harmless, so try it to be safe, after the
            // response is sent so the customer isn't kept waiting for a second timeout.
            defer(fn () => $this->compensate($reservationId, $e));
            throw $e;
        }

        try {
            return $this->saveOrder($customer, $lines, $products, $reservationId);
        } catch (Throwable $e) {
            $this->compensate($reservationId, $e);
            throw $e;
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @param  Collection<int, Product>  $products
     */
    private function saveOrder(User $customer, array $lines, Collection $products, string $reservationId): Order
    {
        return DB::transaction(function () use ($customer, $lines, $products, $reservationId) {
            $items = collect($lines)->map(function ($line) use ($products) {
                $product = $products[$line['product_id']];

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => (int) $line['quantity'],
                    // bcmul keeps money exact (no float rounding)
                    'line_total' => bcmul((string) $product->price, (string) $line['quantity'], 2),
                ];
            });

            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'reservation_id' => $reservationId,
                'total' => $items->reduce(fn ($sum, $item) => bcadd($sum, $item['line_total'], 2), '0.00'),
            ]);
            $order->items()->createMany($items);

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Saving the order failed after stock was reserved: give the stock back.
     */
    private function compensate(string $reservationId, Throwable $cause): void
    {
        try {
            $this->inventory->release($reservationId);
            Log::warning('Order not saved; its stock reservation was released', [
                'reservation_id' => $reservationId,
                'error' => $cause->getMessage(),
            ]);
        } catch (Throwable $releaseError) {
            // Release is idempotent, so this reservation can safely be released again later
            Log::critical('Order not saved and its stock reservation could not be released', [
                'reservation_id' => $reservationId,
                'error' => $cause->getMessage(),
                'release_error' => $releaseError->getMessage(),
            ]);
        }
    }

    /**
     * Move an order to a new status; a cancelled order gives its stock back.
     *
     * @throws ValidationException when the move isn't allowed (e.g. delivered → pending)
     */
    public function changeStatus(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to) {
            // Re-read under lock so two admins can't move the same order at once
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $order->status;

            if (! $from->canBecome($to)) {
                throw ValidationException::withMessages([
                    'status' => __('orders.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]),
                ]);
            }

            // If the inventory service can't be reached the whole change is rolled back (503);
            // release is idempotent, so the admin can simply try again
            if ($to === OrderStatus::Cancelled && $order->reservation_id !== null) {
                $this->inventory->release($order->reservation_id);
            }

            $order->update(['status' => $to]);
            OrderStatusChanged::dispatch($order, $from, $to);

            return $order;
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @param  array<int, int>  $available  product id => quantity in stock
     */
    private function stockError(array $lines, array $available): ValidationException
    {
        $names = Product::whereIn('id', array_keys($available))->pluck('name', 'id');
        $messages = [];

        foreach ($lines as $index => $line) {
            $productId = (int) $line['product_id'];
            if (array_key_exists($productId, $available)) {
                $messages["items.$index.quantity"] = __('orders.insufficient_stock', [
                    'product' => $names[$productId],
                    'available' => $available[$productId],
                ]);
            }
        }

        return ValidationException::withMessages($messages);
    }
}
