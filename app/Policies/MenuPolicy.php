<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('menus.view');
    }

    public function view(User $actor, Menu $menu): bool
    {
        return $actor->hasPermission('menus.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('menus.create');
    }

    public function update(User $actor, Menu $menu): bool
    {
        return $actor->hasPermission('menus.update');
    }

    public function delete(User $actor, Menu $menu): bool
    {
        // System menus carry the permission keys referenced by the routes;
        // deleting one cascades away its matrix rows, leaving the area to
        // the administrator until the menu is seeded again (FIND-005). Only
        // the administrator may do that (Phase 4.4 revision).
        return (! $menu->is_system || $actor->isAdministrator())
            && ! $menu->children()->exists()
            && $actor->hasPermission('menus.delete');
    }
}
