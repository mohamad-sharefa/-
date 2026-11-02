<?php

namespace App\Modules\Users\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage on a route: ->middleware('role:admin') or ->middleware('role:admin,customer')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to perform this action.');
        }

        return $next($request);
    }
}
