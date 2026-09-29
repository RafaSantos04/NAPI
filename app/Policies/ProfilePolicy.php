<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

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
        return ! $profile->is_system
            && $actor->hasPermission('profiles.update');
    }

    public function delete(User $actor, Profile $profile): bool
    {
        return ! $profile->is_system
            && ! $profile->users()->exists()
            && $actor->hasPermission('profiles.delete');
    }

    /**
     * System profiles are immutable, for everyone. Besides that, anyone other
     * than an administrator may not edit the matrix of a profile they hold,
     * nor grant a flag they do not hold themselves.
     *
     * @param  array<mixed, mixed>  $permissions  Requested matrix, not yet validated.
     */
    public function syncMenus(User $actor, Profile $profile, array $permissions = []): bool
    {
        if ($profile->is_system || ! $actor->hasPermission('permissions.update')) {
            return false;
        }

        if ($actor->isAdministrator()) {
            return true;
        }

        if ($actor->profiles->contains($profile)) {
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
}
