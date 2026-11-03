<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Notifications\NewOrderNotification;
use App\Modules\Notifications\Notifications\OrderPlacedNotification;
use App\Modules\Notifications\Notifications\OrderStatusChangedNotification;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Users\Enums\UserRole;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Turns order events into notifications. The Orders module only fires events and knows
 * nothing about who gets told or how.
 */
class SendOrderNotifications
{
    // Emails go out in the store's language; the notifications page shows each reader's own language
    private const MAIL_LOCALE = 'ar';

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('user');

        $order->user->notify((new OrderPlacedNotification($order))->locale(self::MAIL_LOCALE));

        $admins = User::where('role', UserRole::Admin)->whereKeyNot($order->user_id)->get();
        Notification::send($admins, (new NewOrderNotification($order))->locale(self::MAIL_LOCALE));
    }

    public function handleStatusChanged(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('user');

        $order->user->notify((new OrderStatusChangedNotification($order))->locale(self::MAIL_LOCALE));
    }
}
