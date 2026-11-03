<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every error from /api/* leaves in the same shape:
 *
 *     { "status": 404, "code": "not_found", "message": "...", "errors": { ... } }
 *
 * - "code" is stable and meant for programs; "message" is for people, in the request's language.
 * - "errors" (field => messages) is only present for validation errors.
 * - Internal details (class names, files, stack traces) are never sent. With APP_DEBUG on,
 *   a short "debug" block helps during development only.
 */
class ApiExceptionRenderer
{
    /**
     * Codes for the statuses the API uses. Their messages come from lang/{locale}/errors.php.
     */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null; // web pages keep Laravel's normal error pages
        }

        // The error may be thrown before the locale middleware ran (e.g. by auth)
        app()->setLocale($request->getPreferredLanguage(['en', 'ar']));

        [$status, $headers] = match (true) {
            $e instanceof ValidationException => [422, []],
            $e instanceof AuthenticationException => [401, []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getHeaders()],
            default => [500, []],
        };

        $code = self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'bad_request');

        $body = [
            'status' => $status,
            'code' => $code,
            'message' => $this->message($e, $status, $code, $headers),
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        if (config('app.debug') && $status >= 500) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'at' => basename($e->getFile()).':'.$e->getLine(),
            ];
        }

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function message(Throwable $e, int $status, string $code, array $headers): string
    {
        // 401s raised on purpose carry their own, already translated, reason ("wrong password")
        if ($status === 401 && $e instanceof HttpExceptionInterface && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        // Anything else uses our own wording: framework messages can name internal classes
        // ("No query results for model [App\...]") and must not reach the client.
        return __('errors.'.$code, ['seconds' => $headers['Retry-After'] ?? 60]);
    }
}
