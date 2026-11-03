<?php

namespace Tests\Feature\Orders;

use App\Modules\Orders\Models\Order;
use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Requirement 2 failure cases: the inventory service is down or slow, and saving fails after reserving.
 * (The race condition on the last piece is tested against the real service in inventory-service/test.)
 */
class InventoryFailureTest extends TestCase
{
    use RefreshDatabase;

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    private function asCustomer(): static
    {
        return $this->withToken(auth('api')->login(User::factory()->create()));
    }

    public function test_when_the_inventory_service_is_down_an_order_gets_a_clear_503(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $this->inventory()->unavailable = true;

        $this->asCustomer()
            ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], ['Accept-Language' => 'ar'])
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertExactJson([
                'status' => 503,
                'code' => 'service_unavailable',
                'message' => 'خدمة المستودع لا تستجيب حالياً، فلم نتمكن من إتمام العملية. حاول مرة أخرى بعد قليل.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_when_the_inventory_service_is_down_products_still_show_with_unknown_stock(): void
    {
        $product = Product::factory()->withStock(5)->create();
        $this->inventory()->unavailable = true;

        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.stock', null);
        $this->getJson("/api/v1/products/{$product->id}")->assertOk()->assertJsonPath('data.stock', null);
    }

    public function test_products_show_the_stock_reported_by_the_inventory_service(): void
    {
        $product = Product::factory()->withStock(7)->create();

        $this->getJson("/api/v1/products/{$product->id}")->assertOk()->assertJsonPath('data.stock', 7);
    }

    public function test_if_saving_the_order_fails_after_reserving_the_stock_is_given_back(): void
    {
        $product = Product::factory()->withStock(5)->create();

        // Simulate the database failing while the order is being saved
        Order::creating(fn () => throw new RuntimeException('database write failed'));

        $this->asCustomer()
            ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
            ->assertStatus(500)
            ->assertJsonPath('code', 'server_error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $this->inventory()->stockOf([$product->id])[$product->id], 'the 3 reserved pieces were released');
    }

    public function test_a_reservation_that_times_out_is_released_in_case_it_went_through(): void
    {
        $this->withoutDefer(); // run the after-response release right away, inside the test
        $product = Product::factory()->withStock(5)->create();
        $this->inventory()->timeOutAfterReserving = true;

        $this->asCustomer()
            ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->assertStatus(503);

        $this->inventory()->timeOutAfterReserving = false;
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $this->inventory()->stockOf([$product->id])[$product->id], 'no stock lost to the timed-out call');
    }

    public function test_adding_a_product_while_the_inventory_service_is_down_creates_nothing(): void
    {
        Storage::fake('public');
        $category = \App\Modules\Products\Models\Category::factory()->create();
        $this->inventory()->unavailable = true;

        $this->withToken(auth('api')->login(User::factory()->admin()->create()))
            ->post('/api/v1/products', [
                'name' => 'Watch',
                'category_id' => $category->id,
                'price' => '10.00',
                'stock' => 3,
                'image' => UploadedFile::fake()->create('watch.jpg', 50, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(503);

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'the uploaded image was removed');
    }

    public function test_cancelling_releases_the_orders_reservation_in_the_inventory_service(): void
    {
        $product = Product::factory()->withStock(4)->create();
        $orderId = $this->asCustomer()
            ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 4]]])
            ->assertCreated()
            ->json('data.id');
        $this->assertSame(0, $this->inventory()->stockOf([$product->id])[$product->id]);

        auth('api')->forgetUser();
        $this->withToken(auth('api')->login(User::factory()->admin()->create()))
            ->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(4, $this->inventory()->stockOf([$product->id])[$product->id]);
    }
}
