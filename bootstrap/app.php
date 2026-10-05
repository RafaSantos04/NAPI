<?php

use App\Domain\IAM\Exceptions\LastActiveAdministratorException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureUserIsActive;
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
            'token.ability' => EnsureTokenAbility::class,
        ]);

        // Session (admin area): deactivated users lose the session, and
        // `auth`/`guest` redirect within the admin shell.
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.home'));
        $middleware->redirectUsersTo(fn () => route('admin.home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A known business rule rejected by the domain (409), not a server error.
        $exceptions->dontReport(LastActiveAdministratorException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
