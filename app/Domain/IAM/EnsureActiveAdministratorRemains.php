<?php

namespace App\Domain\IAM;

use App\Domain\IAM\Exceptions\LastActiveAdministratorException;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Domain invariant: at least one active, non-deleted user holds the admin
 * profile (INV-08).
 *
 * Every operation that can take the admin profile away from an active user
 * locks the admin profile row first. That serializes concurrent removals, so
 * two requests cannot both see "another admin remains" and both proceed.
 */
final class EnsureActiveAdministratorRemains
{
    /**
     * Call inside the transaction that removes $user's administrator status,
     * before the change is written.
     *
     * @throws LastActiveAdministratorException
     */
    public static function beforeRemoving(User $user): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The administrator invariant must be checked inside a transaction.');
        }

        $admin = Profile::where('slug', Profile::ADMIN)->lockForUpdate()->first();

        if (! $admin || ! self::isActiveAdministrator($user, $admin)) {
            return;
        }

        $othersRemain = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->whereHas('profiles', fn ($query) => $query->whereKey($admin->getKey()))
            ->exists();

        if (! $othersRemain) {
            throw new LastActiveAdministratorException;
        }
    }

    private static function isActiveAdministrator(User $user, Profile $admin): bool
    {
        // Read from the database, not the cached relation: the answer must
        // reflect the state protected by the lock just taken.
        return User::query()
            ->whereKey($user->getKey())
            ->where('is_active', true)
            ->whereHas('profiles', fn ($query) => $query->whereKey($admin->getKey()))
            ->exists();
    }
}
