<?php

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Notifications\NewOrderNotification;
use App\Modules\Notifications\Notifications\OrderPlacedNotification;
use App\Modules\Notifications\Notifications\OrderStatusChangedNotification;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        auth('api')->forgetUser();

        return $this->withToken(auth('api')->login($user));
    }

    private function placeOrder(User $customer): int
    {
        $product = Product::factory()->withStock(10)->create(['price' => '45.00']);

        return $this->actingAsUser($customer)
            ->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_placing_an_order_notifies_the_customer_and_the_admins(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->placeOrder($customer);

        Notification::assertSentTo($customer, OrderPlacedNotification::class,
            fn ($notification, $channels) => $channels === ['database', 'mail']);
        Notification::assertSentTo($admin, NewOrderNotification::class);
        Notification::assertNotSentTo($customer, NewOrderNotification::class);
    }

    public function test_a_status_change_notifies_the_customer(): void
    {
        Notification::fake();
        $order = Order::factory()->create();

        $this->actingAsUser(User::factory()->admin()->create())
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        Notification::assertSentTo($order->user, OrderStatusChangedNotification::class,
            fn ($notification) => $notification->toArray($order->user)['status'] === 'confirmed');
    }

    public function test_a_user_reads_their_notifications_in_their_language(): void
    {
        $customer = User::factory()->create();
        $orderId = $this->placeOrder($customer);

        $this->actingAsUser($customer)
            ->getJson('/api/v1/notifications', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'order_placed')
            ->assertJsonPath('data.0.title', 'تم استلام طلبك')
            ->assertJsonPath('data.0.message', "استلمنا طلبك رقم #{$orderId} بإجمالي \$45.00، وهو الآن قيد الانتظار.")
            ->assertJsonPath('data.0.order_id', $orderId)
            ->assertJsonPath('data.0.read', false);

        $this->actingAsUser($customer)
            ->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.title', 'We received your order');
    }

    public function test_unread_count_and_marking_as_read(): void
    {
        $customer = User::factory()->create();
        $this->placeOrder($customer);
        $this->placeOrder($customer);

        $this->actingAsUser($customer)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 2);

        $id = $customer->notifications()->first()->id;
        $this->actingAsUser($customer)->patchJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->actingAsUser($customer)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 1);

        $this->actingAsUser($customer)->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->actingAsUser($customer)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);
    }

    public function test_a_user_cannot_see_or_touch_someone_elses_notifications(): void
    {
        $owner = User::factory()->create();
        $this->placeOrder($owner);
        $id = $owner->notifications()->first()->id;
        $stranger = User::factory()->create();

        $this->actingAsUser($stranger)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAsUser($stranger)->patchJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_guests_cannot_read_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
