<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language (validation messages etc.) from the Accept-Language header.
 * English stays the default for clients that don't send one.
 */
class SetLocaleFromHeader
{
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->getPreferredLanguage(self::SUPPORTED));

        return $next($request);
    }
}
