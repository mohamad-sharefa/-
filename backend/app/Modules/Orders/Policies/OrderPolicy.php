<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;

class OrderPolicy
{
    /**
     * A customer may only see their own orders; an admin may see any order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }

    /**
     * Only an admin changes an order's status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
