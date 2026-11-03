<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Listeners\SendOrderNotifications;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, [SendOrderNotifications::class, 'handleOrderPlaced']);
        Event::listen(OrderStatusChanged::class, [SendOrderNotifications::class, 'handleStatusChanged']);
    }
}
