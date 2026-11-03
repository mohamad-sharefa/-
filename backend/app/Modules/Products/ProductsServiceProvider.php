<?php

namespace App\Modules\Products;

use App\Modules\Products\Inventory\GrpcInventory;
use App\Modules\Products\Inventory\InMemoryInventory;
use App\Modules\Products\Inventory\InventoryService;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryService::class, function () {
            $config = config('services.inventory');

            return match ($config['driver']) {
                'memory' => new InMemoryInventory,
                default => new GrpcInventory($config['host'], (int) $config['timeout_ms']),
            };
        });
    }
}
