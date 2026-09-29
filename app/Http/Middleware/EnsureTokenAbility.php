<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token capability layer (ADR-0008): derives the required Sanctum ability
 * from the HTTP method, so every authenticated route is covered by default
 * and a new route cannot forget to declare it.
 *
 * Abilities limit what a token may do on behalf of its owner; they never
 * grant anything the owner lacks. First-party session requests carry a
 * TransientToken, which Sanctum treats as holding every ability.
 */
class EnsureTokenAbility
{
    public const READ = 'read';

    public const WRITE = 'write';

    public const DELETE = 'delete';

    /**
     * @var array<int, string>
     */
    public const ABILITIES = [self::READ, self::WRITE, self::DELETE];

    public function handle(Request $request, Closure $next): Response
    {
        $ability = self::requiredFor($request->method());

        if (! $request->user()?->tokenCan($ability)) {
            throw new MissingAbilityException([$ability]);
        }

        return $next($request);
    }

    public static function requiredFor(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => self::READ,
            'DELETE' => self::DELETE,
            default => self::WRITE,
        };
    }
}
