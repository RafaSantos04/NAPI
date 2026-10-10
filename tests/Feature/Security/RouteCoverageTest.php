<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// FIND-021: every route is checked against the rule for its area, so a new
// route that forgets a layer fails here. Rules, not a snapshot of route:list.

/**
 * @return array<int, RoutingRoute>
 */
function routesUnder(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => Str::is($prefix, $route->uri()))
        ->values()
        ->all();
}

function hasPermissionMiddleware(RoutingRoute $route): bool
{
    return collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
}

describe('API route coverage', function () {
    // Self-service routes: their scope is the caller's own account.
    $selfService = ['auth.logout', 'auth.me', 'tokens.index', 'tokens.store', 'tokens.destroy', 'menus.tree'];

    it('authenticates every API route except login and checks the token ability', function () {
        foreach (routesUnder('api/*') as $route) {
            if ($route->getName() === 'auth.login') {
                continue;
            }

            expect($route->gatherMiddleware())
                ->toContain('auth:sanctum', 'token.ability');
        }
    });

    it('gives every authenticated API route a functional permission or a self-service scope', function () use ($selfService) {
        foreach (routesUnder('api/*') as $route) {
            if ($route->getName() === 'auth.login' || in_array($route->getName(), $selfService, true)) {
                continue;
            }

            expect(hasPermissionMiddleware($route))->toBeTrue("{$route->uri()} has no permission: middleware");
        }
    });

    it('never reads the web session on API routes (no statefulApi)', function () {
        foreach (routesUnder('api/*') as $route) {
            $middleware = app('router')->gatherRouteMiddleware($route);

            expect($middleware)
                ->not->toContain(StartSession::class)
                ->not->toContain(EnsureFrontendRequestsAreStateful::class);
        }
    });
});

describe('Admin route coverage', function () {
    it('runs every admin route in the web group (session and CSRF)', function () {
        foreach (routesUnder('admin*') as $route) {
            expect($route->gatherMiddleware())->toContain('web');
        }
    });

    it('requires a session for every admin route but the landing and the login', function () {
        foreach (routesUnder('admin*') as $route) {
            if (in_array($route->getName(), ['admin.home', 'admin.login'], true)) {
                continue;
            }

            expect($route->gatherMiddleware())->toContain('auth');
        }
    });

    it('requires admin access for every admin route but login and logout', function () {
        foreach (routesUnder('admin*') as $route) {
            if (in_array($route->getName(), ['admin.login', 'admin.logout'], true)) {
                continue;
            }

            expect($route->gatherMiddleware())->toContain('admin.access');
        }
    });

    it('gives every admin resource route a functional permission', function () {
        foreach (routesUnder('admin/*') as $route) {
            if (in_array($route->getName(), ['admin.login', 'admin.logout'], true)) {
                continue;
            }

            expect(hasPermissionMiddleware($route))->toBeTrue("{$route->uri()} has no permission: middleware");
        }
    });

    it('keeps guests and users without admin access out of every internal admin route', function () {
        $subject = seededUser('viewer');

        foreach (routesUnder('admin/*') as $route) {
            if (in_array($route->getName(), ['admin.login', 'admin.logout'], true)) {
                continue;
            }

            $method = collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();
            $uri = '/'.str_replace('{user}', $subject->id, $route->uri());

            app('auth')->forgetGuards();
            $this->call($method, $uri)->assertRedirect(route('admin.home'));

            $this->actingAs(seededUser('dev'));
            $this->call($method, $uri)->assertRedirect(route('admin.home'));
            $this->assertGuest('web');
        }

        expect($subject->fresh()->is_active)->toBeTrue();
    });
});

// Phase 5.1: the Security Lab holds deliberately vulnerable code, so its
// boundary is checked route by route.
describe('Security Lab route coverage', function () {
    it('puts every Security Lab route behind the feature flag', function () {
        $routes = routesUnder('admin/security*');

        expect($routes)->not->toBeEmpty();

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('security.lab');
        }
    });

    it('has no route that serves a lab resource by its identifier', function () {
        // The vulnerable read exists only inside RunIdorTest. A route with a
        // parameter here would be a URL that can be probed.
        foreach (routesUnder('admin/security*') as $route) {
            expect($route->parameterNames())->toBe([], "{$route->uri()} takes a parameter");
        }
    });

    it('keeps the Security Lab out of the API and of public routes', function () {
        $elsewhere = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route) => Str::is('admin/security*', $route->uri()))
            ->filter(fn (RoutingRoute $route) => Str::contains($route->uri(), ['security', 'lab', 'idor']));

        expect($elsewhere->map->uri()->all())->toBe([]);
    });
});
