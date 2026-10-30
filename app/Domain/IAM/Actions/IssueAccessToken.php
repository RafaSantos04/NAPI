<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Events\TokenCreated;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues a personal access token for its owner, from the API (POST /tokens)
 * or the admin area (ADR-0013, item 16). Who may issue where is the
 * PersonalAccessTokenPolicy's call; this Action only guarantees how long
 * the token lives.
 */
class IssueAccessToken
{
    /**
     * @param  list<string>  $abilities
     */
    public static function execute(
        User $owner,
        string $name,
        array $abilities,
        CarbonInterface $expiresAt,
        AuditContext $context,
        ?PersonalAccessToken $parent = null,
    ): NewAccessToken {
        // A token minted by another token never outlives it, or a short-lived
        // token could renew itself without a password (FIND-036).
        $ceilings = array_filter([self::globalCeiling(), $parent ? self::expiryOf($parent) : null]);
        $expiresAt = collect([$expiresAt, ...$ceilings])->min();

        return DB::transaction(function () use ($owner, $name, $abilities, $expiresAt, $context): NewAccessToken {
            $token = $owner->createToken($name, $abilities, $expiresAt);

            event(new TokenCreated($owner, $token->accessToken, $context));

            return $token;
        });
    }

    /**
     * When Sanctum stops accepting the token: its own expires_at or the
     * global expiration counted from its creation, whichever comes first.
     * Login tokens have no expires_at and live by the global one alone.
     */
    private static function expiryOf(PersonalAccessToken $token): ?CarbonInterface
    {
        $global = self::minutesOfGlobalExpiration();

        return collect([
            $token->expires_at,
            $global !== null ? $token->created_at?->copy()->addMinutes($global) : null,
        ])->filter()->min();
    }

    private static function globalCeiling(): ?CarbonInterface
    {
        $global = self::minutesOfGlobalExpiration();

        return $global !== null ? now()->addMinutes($global) : null;
    }

    private static function minutesOfGlobalExpiration(): ?int
    {
        $minutes = config('sanctum.expiration');

        return is_numeric($minutes) ? (int) $minutes : null;
    }
}
