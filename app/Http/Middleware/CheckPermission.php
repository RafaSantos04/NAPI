<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional permission gate at the edge of the API, e.g.
 * `permission:users.update`. The decision itself lives in
 * User::hasPermission(), shared with the Policies (ADR-0008).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasPermission($permission)) {
            // 404 instead of 403 so the area's existence is not revealed (ADR-0006).
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
