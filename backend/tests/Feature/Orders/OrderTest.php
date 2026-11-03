<?php

namespace Tests\Feature\Orders;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        auth('api')->forgetUser();

        return $this->withToken(auth('api')->login($user));
    }

    private function placeOrder(User $customer, array $items): Order
    {
        $id = $this->actingAsUser($customer)
            ->postJson('/api/v1/orders', ['items' => $items])
            ->assertCreated()
            ->json('data.id');

        return Order::findOrFail($id);
    }

    private function stockOf(Product $product): int
    {
        return app(InventoryService::class)->stockOf([$product->id])[$product->id];
    }

    public function test_a_customer_can_place_an_order_with_several_products(): void
    {
        Event::fake([OrderPlaced::class]);
        $customer = User::factory()->create();
        $headphones = Product::factory()->withStock(10)->create(['name' => 'Headphones', 'price' => '45.00']);
        $mug = Product::factory()->withStock(5)->create(['name' => 'Mug', 'price' => '8.00']);

        $this->actingAsUser($customer)->postJson('/api/v1/orders', ['items' => [
            ['product_id' => $headphones->id, 'quantity' => 1],
            ['product_id' => $mug->id, 'quantity' => 2],
        ]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '61.00')
            ->assertJsonPath('data.items.1.product_name', 'Mug')
            ->assertJsonPath('data.items.1.line_total', '16.00')
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->assertSame(9, $this->stockOf($headphones));
        $this->assertSame(3, $this->stockOf($mug));
        Event::assertDispatched(OrderPlaced::class);
    }

    public function test_an_order_is_refused_when_stock_is_insufficient_and_nothing_is_reserved(): void
    {
        $customer = User::factory()->create();
        $plenty = Product::factory()->withStock(10)->create();
        $lastPiece = Product::factory()->withStock(1)->create(['name' => 'Last mouse']);

        $this->actingAsUser($customer)->postJson('/api/v1/orders', ['items' => [
            ['product_id' => $plenty->id, 'quantity' => 2],
            ['product_id' => $lastPiece->id, 'quantity' => 3],
        ]], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.quantity' => 'الكمية المتوفرة من "Last mouse" هي 1 فقط.'])
            ->assertJsonMissingValidationErrors(['items.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->stockOf($plenty));
        $this->assertSame(1, $this->stockOf($lastPiece));
    }

    public function test_placing_an_order_validates_the_items(): void
    {
        $product = Product::factory()->create();

        $this->actingAsUser(User::factory()->create())->postJson('/api/v1/orders', ['items' => [
            ['product_id' => $product->id, 'quantity' => 0],
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => 999, 'quantity' => 1],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity', 'items.0.product_id', 'items.2.product_id']);

        $this->actingAsUser(User::factory()->create())->postJson('/api/v1/orders', ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_a_customer_can_view_their_own_order(): void
    {
        $customer = User::factory()->create();
        $order = $this->placeOrder($customer, [['product_id' => Product::factory()->withStock(5)->create()->id, 'quantity' => 1]]);

        $this->actingAsUser($customer)->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.next_statuses', ['confirmed', 'cancelled']);
    }

    public function test_a_customer_cannot_view_someone_elses_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAsUser(User::factory()->create())
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_the_order_list_shows_customers_only_their_own_orders(): void
    {
        $customer = User::factory()->create();
        Order::factory()->count(2)->for($customer)->create();
        Order::factory()->count(3)->create();

        $this->actingAsUser($customer)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.customer.id', $customer->id)
            ->assertJsonPath('data.1.customer.id', $customer->id);
    }

    public function test_an_admin_sees_every_order(): void
    {
        $order = Order::factory()->create();
        Order::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)->getJson('/api/v1/orders')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAsUser($admin)->getJson("/api/v1/orders/{$order->id}")->assertOk();
    }

    public function test_a_customer_cannot_change_an_order_status_even_their_own(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->actingAsUser($customer)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_an_admin_moves_an_order_through_its_statuses(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $order = Order::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach (['confirmed', 'shipped', 'delivered'] as $status) {
            $this->actingAsUser($admin)
                ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertSame([], $order->fresh()->status->nextStatuses());
        Event::assertDispatchedTimes(OrderStatusChanged::class, 3);
    }

    public function test_an_admin_cannot_skip_or_reverse_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = Order::factory()->create();
        $delivered = Order::factory()->status(OrderStatus::Delivered)->create();

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/orders/{$pending->id}/status", ['status' => 'delivered'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'لا يمكن تغيير حالة الطلب من "قيد الانتظار" إلى "تم التسليم".');

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/orders/{$delivered->id}/status", ['status' => 'pending'])
            ->assertUnprocessable();

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/orders/{$pending->id}/status", ['status' => 'lost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelling_an_order_returns_its_stock(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->withStock(5)->create();
        $order = $this->placeOrder($customer, [['product_id' => $product->id, 'quantity' => 3]]);
        $this->assertSame(2, $this->stockOf($product));

        $this->actingAsUser(User::factory()->admin()->create())
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, $this->stockOf($product));
    }

    public function test_guests_cannot_use_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
    }
}
