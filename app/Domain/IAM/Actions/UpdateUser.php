<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\UpdateUserDto;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the API and the admin area. Audits the names of the fields that
 * actually changed, never their values; a no-op update records nothing.
 */
class UpdateUser
{
    public static function execute(User $user, UpdateUserDto $dto, AuditContext $context): User
    {
        DB::transaction(function () use ($user, $dto, $context) {
            $user->update($dto->attributes());

            $changed = array_keys($user->getChanges());
            $fields = array_values(array_diff($changed, [$user->getUpdatedAtColumn()]));

            if ($fields !== []) {
                event(new UserUpdated($user, $fields, $context));
            }
        });

        return $user;
    }
}
