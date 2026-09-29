<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

/**
 * Functional permission comes from the matrix (User::hasPermission); this
 * Policy adds the rules that depend on the specific user being acted on.
 * The last-administrator invariant is enforced by the domain, not here.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    /**
     * Same rule as viewAny, so listing and detail never disagree (FIND-009).
     * Self-service reading goes through /auth/me.
     */
    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.update')
            && $this->mayManage($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return ! $actor->is($user)
            && $actor->hasPermission('users.delete')
            && $this->mayManage($actor, $user);
    }

    /**
     * Anti privilege escalation: an administrator may assign anything (the
     * domain still protects the last active administrator). Anyone else may
     * not change their own profiles, touch an administrator's, or add or
     * remove a profile that grants something they do not hold themselves.
     *
     * @param  array<int, mixed>  $profileIds  Requested set, not yet validated.
     */
    public function assignProfiles(User $actor, User $user, array $profileIds = []): bool
    {
        if (! $actor->hasPermission('users.update')) {
            return false;
        }

        if ($actor->isAdministrator()) {
            return true;
        }

        if ($actor->is($user) || $user->isAdministrator()) {
            return false;
        }

        $requested = collect($profileIds)->filter(fn (mixed $id) => is_string($id));
        $current = $user->profiles()->pluck('profiles.id');
        $changed = $requested->diff($current)->merge($current->diff($requested));

        return Profile::with('menus')
            ->whereKey($changed->all())
            ->get()
            ->every(fn (Profile $profile) => $actor->holdsPermissionsOf($profile));
    }

    /**
     * Only an administrator may change or delete an administrator account.
     */
    private function mayManage(User $actor, User $user): bool
    {
        return $actor->isAdministrator() || ! $user->isAdministrator();
    }
}
