<?php

use App\Domain\ApiInventory\Exceptions\ApiImportInProgressException;
use App\Domain\ApiInventory\Exceptions\ApiSpecificationLifecycleException;
use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use App\Domain\IAM\Exceptions\LastActiveAdministratorException;
use App\Domain\IAM\Exceptions\TooManyLoginAttemptsException;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\EnvironmentLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Exceptions\LastActiveOrganizationOwnerException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureSecurityLabEnabled;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureUserCanAccessAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LimitRequestBodySize;
use App\Http\Middleware\RejectInvalidUtf8;
use App\Http\Middleware\ResolveOrganizationContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'admin.access' => EnsureUserCanAccessAdmin::class,
            'security.lab' => EnsureSecurityLabEnabled::class,
            'token.ability' => EnsureTokenAbility::class,
            'organization.context' => ResolveOrganizationContext::class,
        ]);

        // Input limits run first in both groups, before the session,
        // authentication and route binding (ADR-0013): body size, then
        // UTF-8 (which may read the body). The limit is named with its
        // parameter so a route can exclude exactly this one and state its
        // own (ADR-0014, decision 13): Laravel excludes a bare class name
        // with every parameter.
        $inputLimits = [LimitRequestBodySize::class.':'.LimitRequestBodySize::MAX_BYTES, RejectInvalidUtf8::class];
        $middleware->api(prepend: $inputLimits);

        // Session (admin area): deactivated users lose the session, and
        // `auth`/`guest` redirect within the admin shell.
        $middleware->web(prepend: $inputLimits, append: [EnsureUserIsActive::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.home'));
        $middleware->redirectUsersTo(fn () => route('admin.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A known business rule rejected by the domain (409), not a server error.
        $exceptions->dontReport(LastActiveAdministratorException::class);
        $exceptions->dontReport(LastActiveOrganizationOwnerException::class);
        $exceptions->dontReport(ProjectLifecycleException::class);
        $exceptions->dontReport(ApplicationLifecycleException::class);
        $exceptions->dontReport(EnvironmentLifecycleException::class);
        $exceptions->dontReport(ApiSpecificationLifecycleException::class);
        $exceptions->dontReport(ApiImportInProgressException::class);
        // A refused document (422), already in the audit trail.
        $exceptions->dontReport(OpenApiParseException::class);
        // An expected refusal, already signalled by the Lockout event.
        $exceptions->dontReport(TooManyLoginAttemptsException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
