<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasProfile('admin') || $user->hasProfile('dev');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasProfile('admin');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->hasProfile('admin');
    }

    public function delete(User $user, Menu $menu): bool
    {
        // Não pode deletar se tem filhos
        if ($menu->children()->exists()) {
            return false;
        }

        return $user->hasProfile('admin');
    }
}
