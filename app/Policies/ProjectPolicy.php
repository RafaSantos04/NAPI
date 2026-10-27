<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission('projects.view', $organization);
    }

    public function view(User $user, Project $project, Organization $organization): bool
    {
        return $project->organization_id === $organization->id
            && $user->hasPermission('projects.view', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission('projects.create', $organization);
    }

    public function update(User $user, Project $project, Organization $organization): bool
    {
        return $project->organization_id === $organization->id
            && $user->hasPermission('projects.update', $organization);
    }

    /**
     * `projects.archive` reaches any project of the organization;
     * `projects.archive_own` only the ones the user's membership created
     * (ADR-0012). The role itself is never tested here.
     */
    public function archive(User $user, Project $project, Organization $organization): bool
    {
        if ($project->organization_id !== $organization->id) {
            return false;
        }

        if ($user->hasPermission('projects.archive', $organization)) {
            return true;
        }

        return $user->hasPermission('projects.archive_own', $organization)
            && $project->created_by_membership_id === $user->organizationMemberships()
                ->whereBelongsTo($organization)
                ->value('id');
    }

    public function restore(User $user, Project $project, Organization $organization): bool
    {
        return $this->archive($user, $project, $organization);
    }
}
