<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasProfile('admin') || $user->hasProfile('dev');
    }

    public function view(User $user, User $userToView): bool
    {
        // Ele mesmo sempre pode ver
        if ($user->id === $userToView->id) {
            return true;
        }

        // Admin pode ver qualquer um
        if ($user->hasProfile('admin')) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasProfile('admin');
    }

    public function update(User $user, User $userToUpdate): bool
    {
        // Admin pode atualizar qualquer um, exceto a si mesmo (não pode remover admin)
        if ($user->hasProfile('admin') && $user->id !== $userToUpdate->id) {
            return true;
        }

        // Usuário pode atualizar a si mesmo
        return $user->id === $userToUpdate->id;
    }

    public function delete(User $user, User $userToDelete): bool
    {
        // Admin não pode deletar a si mesmo
        if ($user->id === $userToDelete->id) {
            return false;
        }

        // Admin pode deletar qualquer um
        if ($user->hasProfile('admin')) {
            return true;
        }

        return false;
    }
}
