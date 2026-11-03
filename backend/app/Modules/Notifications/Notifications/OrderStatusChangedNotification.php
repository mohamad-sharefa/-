<?php

namespace App\Modules\Notifications\Notifications;

/**
 * To the customer: your order moved to a new status.
 */
class OrderStatusChangedNotification extends OrderNotification
{
    public function kind(): string
    {
        return 'order_status_changed';
    }

    protected function data(): array
    {
        return [
            'status' => $this->order->status->value,
        ];
    }
}
