<?php

namespace App\Domain\IAM\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\IAM\EnsureActiveAdministratorRemains;
use App\Events\UserDeactivated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates an account and cuts its existing access: API tokens are
 * deleted and stored web sessions removed. Deactivating can take away an
 * administrator, so the domain invariant runs in the same transaction, as
 * for DeleteUser (Phase 4.2).
 *
 * Enforcement does not depend on this cleanup: Sanctum rejects tokens of
 * inactive users and EnsureUserIsActive ends their sessions. Removing them
 * here keeps a later reactivation from bringing old access back.
 */
class DeactivateUser
{
    public static function execute(User $user, AuditContext $context): void
    {
        if (! $user->is_active) {
            return;
        }

        DB::transaction(function () use ($user, $context) {
            EnsureActiveAdministratorRemains::beforeRemoving($user);

            // Before the update, so the count reaches the audit entry (the
            // model hook would delete them anyway).
            $revokedTokens = $user->tokens()->delete();

            $user->update(['is_active' => false]);

            event(new UserDeactivated($user, $revokedTokens, self::endWebSessions($user), $context));
        });
    }

    /**
     * Only the database session driver can find a user's sessions. With any
     * other driver the session ends on its next request (EnsureUserIsActive).
     */
    private static function endWebSessions(User $user): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
