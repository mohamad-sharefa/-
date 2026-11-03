<?php

namespace Tests\Feature;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * The rules every endpoint follows: one error shape, no internal details, security headers, rate limits.
 */
class ApiStandardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_use_the_unified_shape(): void
    {
        $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertExactJsonStructure(['status', 'code', 'message', 'errors' => ['email', 'password']])
            ->assertJson(['status' => 422, 'code' => 'validation_failed', 'message' => 'البيانات المرسلة غير صحيحة.']);
    }

    public function test_unauthenticated_and_forbidden_errors_use_the_unified_shape(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertExactJson(['status' => 401, 'code' => 'unauthenticated', 'message' => 'You need to log in first.']);

        $order = Order::factory()->create();
        $this->withToken(auth('api')->login(User::factory()->create()))
            ->getJson("/api/v1/orders/{$order->id}", ['Accept-Language' => 'ar'])
            ->assertForbidden()
            ->assertExactJson(['status' => 403, 'code' => 'forbidden', 'message' => 'لا تملك صلاحية تنفيذ هذه العملية.']);
    }

    public function test_wrong_password_keeps_its_specific_message(): void
    {
        User::factory()->create(['email' => 'ali@shop.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ali@shop.test', 'password' => 'nope'], ['Accept-Language' => 'ar'])
            ->assertUnauthorized()
            ->assertJson(['code' => 'unauthenticated', 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
    }

    public function test_not_found_errors_never_reveal_internal_class_names(): void
    {
        $response = $this->getJson('/api/v1/products/999')
            ->assertNotFound()
            ->assertExactJson(['status' => 404, 'code' => 'not_found', 'message' => 'The requested resource was not found.']);

        $this->assertStringNotContainsString('App\\', $response->getContent());

        $this->get('/api/v1/no-such-endpoint')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->deleteJson('/api/v1/products')->assertMethodNotAllowed()->assertJsonPath('code', 'method_not_allowed');
    }

    public function test_server_errors_hide_the_exception_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/v1/test-crash', fn () => throw new RuntimeException('database password is hunter2'));

        $response = $this->getJson('/api/v1/test-crash')
            ->assertStatus(500)
            ->assertExactJson(['status' => 500, 'code' => 'server_error', 'message' => 'Something went wrong on our side. Please try again.']);

        $this->assertStringNotContainsString('hunter2', $response->getContent());
    }

    public function test_too_many_requests_uses_the_unified_shape_and_retry_after(): void
    {
        User::factory()->create(['email' => 'ali@shop.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ali@shop.test', 'password' => 'nope']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ali@shop.test', 'password' => 'nope'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_the_whole_api_is_rate_limited(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 120);
    }

    public function test_security_headers_are_sent_on_api_responses(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_pages_only_run_scripts_carrying_this_requests_nonce(): void
    {
        $response = $this->get('/products')->assertOk();

        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $match);
        $this->assertNotEmpty($match[1] ?? null, 'The policy should contain a nonce');
        $this->assertStringContainsString('<script nonce="'.$match[1].'">', $response->getContent());
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));

        // A new nonce on every request
        preg_match("/'nonce-([^']+)'/", $this->get('/products')->headers->get('Content-Security-Policy'), $second);
        $this->assertNotSame($match[1], $second[1]);
    }
}
