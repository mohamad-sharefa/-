<?php

namespace App\Modules\Products;

use App\Modules\Products\Inventory\DatabaseInventory;
use App\Modules\Products\Inventory\InventoryService;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryService::class, DatabaseInventory::class);
    }
}
