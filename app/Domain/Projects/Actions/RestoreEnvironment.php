<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\EnvironmentLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\EnvironmentRestored;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class RestoreEnvironment
{
    public static function execute(
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $project, $application, $environment, $context) {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

            // Archived ancestors have to be restored first.
            if ($lockedProject->isArchived()) {
                throw ProjectLifecycleException::archived($lockedProject);
            }

            $lockedApplication = Application::query()
                ->whereKey($application->id)
                ->whereBelongsTo($lockedProject)
                ->sharedLock()
                ->firstOrFail();

            if ($lockedApplication->isArchived()) {
                throw ApplicationLifecycleException::archived($lockedApplication);
            }

            $locked = Environment::query()
                ->whereKey($environment->id)
                ->whereBelongsTo($lockedApplication)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isArchived()) {
                throw EnvironmentLifecycleException::notArchived($locked);
            }

            $locked->archived_at = null;
            $locked->save();

            event(new EnvironmentRestored($locked, $context->forOrganization($organization->id)));
        });
    }
}
