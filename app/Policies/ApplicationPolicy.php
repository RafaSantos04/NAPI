<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user, Project $project, Organization $organization): bool
    {
        return $project->organization_id === $organization->id
            && $user->hasPermission('applications.view', $organization);
    }

    public function view(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isWithin($application, $project, $organization)
            && $user->hasPermission('applications.view', $organization);
    }

    public function create(User $user, Project $project, Organization $organization): bool
    {
        return $project->organization_id === $organization->id
            && $user->hasPermission('applications.create', $organization);
    }

    public function update(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isWithin($application, $project, $organization)
            && $user->hasPermission('applications.update', $organization);
    }

    /**
     * `applications.archive` reaches any application of the organization;
     * `applications.archive_own` only those under a project the user's
     * membership created: the project's creator governs its tree (ADR-0012).
     */
    public function archive(User $user, Application $application, Project $project, Organization $organization): bool
    {
        if (! $this->isWithin($application, $project, $organization)) {
            return false;
        }

        if ($user->hasPermission('applications.archive', $organization)) {
            return true;
        }

        return $user->hasPermission('applications.archive_own', $organization)
            && $project->created_by_membership_id === $user->organizationMemberships()
                ->whereBelongsTo($organization)
                ->value('id');
    }

    public function restore(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->archive($user, $application, $project, $organization);
    }

    /**
     * Scoped binding already nests the route parameters; the Policy confirms
     * the whole chain again instead of relying on it.
     */
    private function isWithin(Application $application, Project $project, Organization $organization): bool
    {
        return $application->project_id === $project->id
            && $application->organization_id === $organization->id
            && $project->organization_id === $organization->id;
    }
}
