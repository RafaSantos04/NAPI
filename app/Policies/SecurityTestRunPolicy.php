<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may use the Security Lab, from the same matrix as every other area
 * (User::hasPermission, ADR-0008). Running a test creates a run, so it maps
 * to the `create` action; there is no separate "execute" permission.
 */
class SecurityTestRunPolicy
{
    public function viewAny(User $operator): bool
    {
        return $operator->hasPermission('security-lab.view');
    }

    public function create(User $operator): bool
    {
        return $operator->hasPermission('security-lab.create');
    }
}
