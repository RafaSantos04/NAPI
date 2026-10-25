<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

class OrganizationMembershipPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('members.view', $organization);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrganizationMembership $organizationMembership, Organization $organization): bool
    {
        return $organizationMembership->organization_id === $organization->id
            && $user->hasPermission('members.view', $organization);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('members.manage', $organization);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrganizationMembership $organizationMembership, Organization $organization): bool
    {
        return $organizationMembership->organization_id === $organization->id
            && $user->hasPermission('members.manage', $organization);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrganizationMembership $organizationMembership, Organization $organization): bool
    {
        return $organizationMembership->organization_id === $organization->id
            && $user->hasPermission('members.manage', $organization);
    }
}
