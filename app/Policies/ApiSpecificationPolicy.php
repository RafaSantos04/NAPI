<?php

namespace App\Policies;

use App\Models\ApiSpecification;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * Listing and creating are nested under the application; the other routes
 * are shallow, under the organization only (ADR-0014, decision 8). The
 * composite foreign keys hold the rest of the chain in the database.
 */
class ApiSpecificationPolicy
{
    public function viewAny(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isApplicationWithin($application, $project, $organization)
            && $user->hasPermission('api_specifications.view', $organization);
    }

    public function create(User $user, Application $application, Project $project, Organization $organization): bool
    {
        return $this->isApplicationWithin($application, $project, $organization)
            && $user->hasPermission('api_specifications.create', $organization);
    }

    public function view(User $user, ApiSpecification $specification, Organization $organization): bool
    {
        return $specification->organization_id === $organization->id
            && $user->hasPermission('api_specifications.view', $organization);
    }

    public function update(User $user, ApiSpecification $specification, Organization $organization): bool
    {
        return $specification->organization_id === $organization->id
            && $user->hasPermission('api_specifications.update', $organization);
    }

    /**
     * `api_specifications.archive` reaches any specification of the
     * organization; `api_specifications.archive_own` only those under a
     * project the user's membership created: the project's creator governs
     * its tree (ADR-0012).
     */
    public function archive(User $user, ApiSpecification $specification, Organization $organization): bool
    {
        if ($specification->organization_id !== $organization->id) {
            return false;
        }

        if ($user->hasPermission('api_specifications.archive', $organization)) {
            return true;
        }

        return $user->hasPermission('api_specifications.archive_own', $organization)
            && $specification->application->project->created_by_membership_id === $user->organizationMemberships()
                ->whereBelongsTo($organization)
                ->value('id');
    }

    public function restore(User $user, ApiSpecification $specification, Organization $organization): bool
    {
        return $this->archive($user, $specification, $organization);
    }

    /**
     * Scoped binding already nests the route parameters; the Policy confirms
     * the whole chain again instead of relying on it.
     */
    private function isApplicationWithin(Application $application, Project $project, Organization $organization): bool
    {
        return $application->project_id === $project->id
            && $application->organization_id === $organization->id
            && $project->organization_id === $organization->id;
    }
}
