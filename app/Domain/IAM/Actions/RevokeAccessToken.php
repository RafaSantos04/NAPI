<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Events\TokenRevoked;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revokes one of the owner's tokens, from the API or the admin area. Callers
 * find the token among the owner's own (anything else is a 404 for them).
 */
class RevokeAccessToken
{
    public static function execute(User $owner, PersonalAccessToken $token, AuditContext $context): void
    {
        DB::transaction(function () use ($owner, $token, $context) {
            $token->delete();

            event(new TokenRevoked($owner, $token, $context));
        });
    }
}
