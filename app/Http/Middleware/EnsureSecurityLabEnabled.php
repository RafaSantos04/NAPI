<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flag of the Security Lab (`security.lab`). Checked on every
 * request rather than when routes are registered, so the answer follows the
 * configuration even with cached routes. Disabled means the area does not
 * exist: 404, for everyone, administrators included.
 */
class EnsureSecurityLabEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('security.lab.enabled'), 404);

        return $next($request);
    }
}
