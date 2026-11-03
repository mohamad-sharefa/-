<?php

namespace App\Modules\Orders\Enums;

/**
 * Order lifecycle: pending → confirmed → shipped → delivered.
 * An order can be cancelled until it is shipped.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->nextStatuses(), true);
    }

    public function label(): string
    {
        return __('orders.statuses.'.$this->value);
    }
}
