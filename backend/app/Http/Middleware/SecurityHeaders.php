<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic security headers on every response.
 *
 * Pages get a Content-Security-Policy that only runs scripts from this site, the docs CDN,
 * or inline scripts carrying this request's nonce (views print it with Vite::cspNonce()).
 * An injected <script> has no nonce, so the browser refuses to run it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $isHtml = str_contains((string) $headers->get('Content-Type'), 'text/html');
        $headers->set('Content-Security-Policy', $isHtml ? $this->pagePolicy($nonce) : "default-src 'none'; frame-ancestors 'none'");

        return $response;
    }

    private function pagePolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net",
            // Swagger UI sets inline styles; style injection can't run code
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com",
            // data: for Swagger's icons, blob: for the product image preview
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
