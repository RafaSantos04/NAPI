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
        // deleting one would cascade away its matrix rows (FIND-005).
        return ! $menu->is_system
            && ! $menu->children()->exists()
            && $actor->hasPermission('menus.delete');
    }
}
