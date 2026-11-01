<?php

namespace App\Domain\Projects;

use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The lock order of every write in the project tree (ADR-0012): ancestors
 * top-down with shared locks, each one rejected when archived, before the
 * caller locks its own row FOR UPDATE. Shared locks let writes under the same
 * ancestor run together while a concurrent archive of it waits.
 */
class ProjectTreeLock
{
    public static function activeProject(Organization $organization, string $projectId): Project
    {
        self::requireTransaction();

        $project = Project::query()
            ->whereKey($projectId)
            ->whereBelongsTo($organization)
            ->sharedLock()
            ->firstOrFail();

        // Under an archived project the whole tree is read-only, and it has
        // to be restored before anything below it.
        if ($project->isArchived()) {
            throw ProjectLifecycleException::archived($project);
        }

        return $project;
    }

    /**
     * Reading `project_id` before the lock is safe: a trigger keeps it
     * immutable.
     */
    public static function activeApplication(Organization $organization, Application $application): Application
    {
        $project = self::activeProject($organization, $application->project_id);

        $locked = Application::query()
            ->whereKey($application->id)
            ->whereBelongsTo($project)
            ->sharedLock()
            ->firstOrFail();

        if ($locked->isArchived()) {
            throw ApplicationLifecycleException::archived($locked);
        }

        return $locked;
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The project tree must be locked inside a transaction.');
        }
    }
}
