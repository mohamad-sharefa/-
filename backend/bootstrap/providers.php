<?php

use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Products\ProductsServiceProvider;
use App\Modules\Users\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    UsersServiceProvider::class,
    ProductsServiceProvider::class,
    NotificationsServiceProvider::class,
];
