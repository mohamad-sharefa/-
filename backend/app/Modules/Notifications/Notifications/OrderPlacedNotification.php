<?php

namespace App\Modules\Notifications\Notifications;

/**
 * To the customer: we received your order.
 */
class OrderPlacedNotification extends OrderNotification
{
    public function kind(): string
    {
        return 'order_placed';
    }

    protected function data(): array
    {
        return [
            'total' => $this->order->total,
            'status' => $this->order->status->value,
        ];
    }
}
