<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Inventory\InsufficientStockException;
use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Reserve stock for every line and create the order, in one transaction.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     *
     * @throws ValidationException when a product doesn't have enough stock
     */
    public function place(User $customer, array $lines): Order
    {
        $quantities = collect($lines)->mapWithKeys(fn ($line) => [(int) $line['product_id'] => (int) $line['quantity']])->all();

        return DB::transaction(function () use ($customer, $lines, $quantities) {
            try {
                $this->inventory->reserve($quantities);
            } catch (InsufficientStockException $e) {
                throw $this->stockError($lines, $e->available);
            }

            $products = Product::whereIn('id', array_keys($quantities))->get()->keyBy('id');

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
                'total' => $items->reduce(fn ($sum, $item) => bcadd($sum, $item['line_total'], 2), '0.00'),
            ]);
            $order->items()->createMany($items);

            OrderPlaced::dispatch($order);

            return $order;
        });
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

            if ($to === OrderStatus::Cancelled) {
                $this->inventory->release($order->items()->pluck('quantity', 'product_id')->all());
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
