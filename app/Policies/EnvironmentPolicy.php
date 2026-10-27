<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class EnvironmentPolicy
{
    public function viewAny(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isApplicationWithin($application, $project, $organization)
            && $user->hasPermission('environments.view', $organization);
    }

    public function view(User $user, Environment $environment, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isWithin($environment, $application, $project, $organization)
            && $user->hasPermission('environments.view', $organization);
    }

    public function create(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isApplicationWithin($application, $project, $organization)
            && $user->hasPermission('environments.create', $organization);
    }

    public function update(User $user, Environment $environment, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isWithin($environment, $application, $project, $organization)
            && $user->hasPermission('environments.update', $organization);
    }

    /**
     * `environments.archive` reaches any environment of the organization;
     * `environments.archive_own` only those under a project the user's
     * membership created (ADR-0012).
     */
    public function archive(User $user, Environment $environment, Application $application, Project $project, Organization $organization): bool
    {
        if (! $this->isWithin($environment, $application, $project, $organization)) {
            return false;
        }

        if ($user->hasPermission('environments.archive', $organization)) {
            return true;
        }

        return $user->hasPermission('environments.archive_own', $organization)
            && $project->created_by_membership_id === $user->organizationMemberships()
                ->whereBelongsTo($organization)
                ->value('id');
    }

    public function restore(User $user, Environment $environment, Application $application, Project $project, Organization $organization): bool
    {
        return $this->archive($user, $environment, $application, $project, $organization);
    }

    /**
     * Scoped binding already nests the route parameters; the Policy confirms
     * the whole chain again instead of relying on it.
     */
    private function isWithin(Environment $environment, Application $application, Project $project, Organization $organization): bool
    {
        return $environment->application_id === $application->id
            && $environment->organization_id === $organization->id
            && $this->isApplicationWithin($application, $project, $organization);
    }

    private function isApplicationWithin(Application $application, Project $project, Organization $organization): bool
    {
        return $application->project_id === $project->id
            && $application->organization_id === $organization->id
            && $project->organization_id === $organization->id;
    }
}
