<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_as_a_customer(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sara',
            'email' => 'sara@shop.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'sara@shop.test')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token' => ['access_token', 'token_type', 'expires_in']]]);
    }

    public function test_registration_ignores_a_requested_admin_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@shop.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('data.user.role', 'customer');
    }

    public function test_registration_validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_validation_messages_follow_the_accept_language_header(): void
    {
        $this->postJson('/api/v1/auth/register', [], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'حقل البريد الإلكتروني مطلوب.');

        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }

    public function test_a_user_can_log_in_and_read_their_profile(): void
    {
        $user = User::factory()->create(['email' => 'ali@shop.test']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ali@shop.test',
            'password' => 'password',
        ])->assertOk()->json('data.token.access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'ali@shop.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ali@shop.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_protected_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_unauthenticated_requests_get_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['status' => 401, 'code' => 'unauthenticated']);
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'ali@shop.test']);
        $credentials = ['email' => 'ali@shop.test', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_a_logged_out_token_can_no_longer_be_used(): void
    {
        $token = auth('api')->login(User::factory()->create());

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        // Forget the user cached by the guard so the next request re-checks the token
        auth('api')->forgetUser();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_role_middleware_allows_only_the_listed_roles(): void
    {
        Route::middleware(['api', 'auth:api', 'role:admin'])
            ->get('/api/v1/test-admin-only', fn () => ['ok' => true]);

        $customerToken = auth('api')->login(User::factory()->create());
        $this->withToken($customerToken)->getJson('/api/v1/test-admin-only')->assertForbidden();

        auth('api')->forgetUser();

        $adminToken = auth('api')->login(User::factory()->admin()->create());
        $this->withToken($adminToken)->getJson('/api/v1/test-admin-only')->assertOk();
    }
}
