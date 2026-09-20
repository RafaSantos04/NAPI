<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasProfile('admin') || $user->hasProfile('dev');
    }

    public function view(User $user, Profile $profile): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasProfile('admin');
    }

    public function update(User $user, Profile $profile): bool
    {
        // Não pode editar perfis de sistema
        if ($profile->is_system) {
            return false;
        }

        return $user->hasProfile('admin');
    }

    public function delete(User $user, Profile $profile): bool
    {
        // Não pode deletar perfis de sistema
        if ($profile->is_system) {
            return false;
        }

        // Não pode deletar se usuários têm esse perfil
        if ($profile->users()->exists()) {
            return false;
        }

        return $user->hasProfile('admin');
    }
}
