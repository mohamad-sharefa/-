<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Enums\UserRole;
use App\Modules\Users\Http\Requests\LoginRequest;
use App\Modules\Users\Http\Requests\RegisterRequest;
use App\Modules\Users\Http\Resources\UserResource;
use App\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->validated());
        // Public registration always creates customers; admins are created by a seeder
        $user->role = UserRole::Customer;
        $user->save();

        $token = auth('api')->login($user);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $this->tokenPayload($token),
            ],
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth('api')->attempt($request->validated());

        if (! $token) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid email or password.');
        }

        return response()->json([
            'data' => [
                'user' => new UserResource(auth('api')->user()),
                'token' => $this->tokenPayload($token),
            ],
        ]);
    }

    public function me(): UserResource
    {
        return new UserResource(auth('api')->user());
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
        } catch (JWTException) {
            abort(Response::HTTP_UNAUTHORIZED, 'Token cannot be refreshed.');
        }

        return response()->json(['data' => ['token' => $this->tokenPayload($token)]]);
    }

    public function logout(): Response
    {
        // Adds the token to the blacklist so it cannot be used again
        auth('api')->logout();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }
}
