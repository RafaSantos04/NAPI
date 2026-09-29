<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\IAM\EnsureActiveAdministratorRemains;
use App\Events\UserDeleted;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a user. An Action rather than a controller call because a
 * deletion can remove an administrator, which the domain invariant guards
 * regardless of who is allowed to delete (ADR-0007).
 */
class DeleteUser
{
    public static function execute(User $user, AuditContext $context): void
    {
        DB::transaction(function () use ($user, $context) {
            EnsureActiveAdministratorRemains::beforeRemoving($user);

            $user->delete();

            event(new UserDeleted($user, $context));
        });
    }
}
