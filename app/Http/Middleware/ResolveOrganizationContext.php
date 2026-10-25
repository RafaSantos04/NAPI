<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\OrganizationContext;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! $organization instanceof Organization) {
            throw new LogicException('Organization route binding must run before organization context resolution.');
        }

        // Identifies the requested tenant. Permission middleware and Policies
        // still decide whether the authenticated user may enter it.
        $this->context->set($organization);

        try {
            return $next($request);
        } finally {
            // Prevents stale tenant state in tests and long-running workers.
            $this->context->clear();
        }
    }
}
