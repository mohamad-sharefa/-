<?php

namespace App\Console\Commands;

use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Requirement 2 demonstration: several customers order the last piece at the same moment.
 *
 *   docker compose exec app php artisan demo:race --buyers=10
 *
 * Sends the orders through the real API (nginx → Laravel → gRPC → inventory service) at once.
 * Exactly one should be created; the others are refused because the piece is gone.
 */
class DemoRaceCommand extends Command
{
    protected $signature = 'demo:race
        {--buyers=10 : How many customers order at the same moment}
        {--url=http://nginx : Base URL of the store inside the Docker network}';

    protected $description = 'Several orders for the last piece at the same moment: only one may succeed';

    public function handle(InventoryService $inventory): int
    {
        $buyers = max(2, (int) $this->option('buyers'));

        $product = Product::firstOrCreate(
            ['name' => 'آخر قطعة (عرض تجريبي)'],
            ['category_id' => Category::firstOrFail()->id, 'price' => '99.00', 'description' => 'منتج للعرض التجريبي لمشكلة الـ Race Condition.'],
        );
        $inventory->setStock($product->id, 1);
        $this->info("Product #{$product->id} \"{$product->name}\" now has exactly 1 piece in the inventory service.");

        $tokens = collect(range(1, $buyers))->map(function (int $i) {
            $user = User::firstOrCreate(
                ['email' => "race-buyer-$i@shop.test"],
                ['name' => "Race buyer $i", 'password' => Str::password(32)],
            );

            return auth('api')->login($user);
        });

        $this->line("Sending $buyers orders at the same moment...");
        $started = microtime(true);

        $responses = Http::pool(fn (Pool $pool) => $tokens->map(
            fn (string $token) => $pool->withToken($token)
                ->acceptJson()
                ->withHeaders(['Accept-Language' => 'ar'])
                ->timeout(30)
                ->post(rtrim($this->option('url'), '/').'/api/v1/orders', [
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                ]),
        )->all());

        $elapsed = round((microtime(true) - $started) * 1000);

        $rows = collect($responses)->map(function ($response, $index) {
            if ($response instanceof \Throwable) {
                return ['Race buyer '.($index + 1), '-', 'request failed: '.$response->getMessage()];
            }

            $result = $response->status() === 201
                ? 'order #'.$response->json('data.id').' created'
                : ($response->json('errors')
                    ? collect($response->json('errors'))->flatten()->first()
                    : $response->json('message'));

            return ['Race buyer '.($index + 1), $response->status(), $result];
        });

        $this->table(['Customer', 'HTTP', 'Result'], $rows->all());

        $created = $rows->where(1, 201)->count();
        $left = $inventory->stockOf([$product->id])[$product->id];

        $this->newLine();
        $this->line("All $buyers requests finished in {$elapsed} ms.");
        $this->line("Orders created: <options=bold>$created</>   Stock left: <options=bold>$left</>");

        if ($created === 1 && $left === 0) {
            $this->info('Only one order got the last piece, and stock never went below zero.');

            return self::SUCCESS;
        }

        $this->error('Unexpected result: exactly one order should have been created.');

        return self::FAILURE;
    }
}
