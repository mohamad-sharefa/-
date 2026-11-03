<?php

namespace App\Modules\Notifications\Notifications;

/**
 * To the admins: a customer placed a new order.
 */
class NewOrderNotification extends OrderNotification
{
    public function kind(): string
    {
        return 'new_order';
    }

    protected function data(): array
    {
        return [
            'total' => $this->order->total,
            'customer_name' => $this->order->user->name,
        ];
    }
}
