<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

/**
 * A system profile is managed by the administrator only: it can be edited,
 * deleted and have its matrix changed by them, and by nobody else, whatever
 * permissions they hold (Phase 4.4 revision; before it, system profiles were
 * immutable for everyone).
 *
 * The administrator profile itself stays fixed for everyone: it cannot be
 * deleted (see delete()), and its matrix does not apply, since it holds every
 * permission implicitly. Its identifier is guarded by ProfileUpdateRequest
 * and UpdateProfile.
 */
class ProfilePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('profiles.view');
    }

    public function view(User $actor, Profile $profile): bool
    {
        return $actor->hasPermission('profiles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('profiles.create');
    }

    public function update(User $actor, Profile $profile): bool
    {
        return $this->reachable($actor, $profile)
            && $actor->hasPermission('profiles.update');
    }

    /**
     * The administrator profile is never deletable without a rule of its
     * own: at least one active user always holds it (INV-08), and a profile
     * with users is not deleted.
     */
    public function delete(User $actor, Profile $profile): bool
    {
        return $this->reachable($actor, $profile)
            && ! $profile->users()->exists()
            && $actor->hasPermission('profiles.delete');
    }

    /**
     * Besides the system rule, anyone other than an administrator may not
     * edit the matrix of a profile they hold, nor grant a flag they do not
     * hold themselves.
     *
     * @param  array<mixed, mixed>  $permissions  Requested matrix, not yet validated.
     */
    public function syncMenus(User $actor, Profile $profile, array $permissions = []): bool
    {
        if ($profile->isAdministrator() || ! $actor->hasPermission('permissions.update')) {
            return false;
        }

        if ($actor->isAdministrator()) {
            return true;
        }

        if ($profile->is_system || $actor->profiles->contains($profile)) {
            return false;
        }

        $menus = Menu::whereKey(array_keys($permissions))->pluck('key', 'id');

        foreach ($permissions as $menuId => $flags) {
            foreach ((array) $flags as $column => $granted) {
                $action = is_string($column) ? substr($column, strlen('can_')) : '';

                // Over-approximates "granted" so malformed input is denied here
                // rather than slipping past before validation rejects it.
                if ($granted && ! $actor->hasPermission("{$menus->get($menuId)}.{$action}")) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Only an administrator reaches a system profile.
     */
    private function reachable(User $actor, Profile $profile): bool
    {
        return ! $profile->is_system || $actor->isAdministrator();
    }
}
