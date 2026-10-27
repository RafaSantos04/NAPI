<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\ApplicationRestored;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class RestoreApplication
{
    public static function execute(Organization $organization, Project $project, Application $application, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $project, $application, $context) {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

            // The project has to be restored first.
            if ($lockedProject->isArchived()) {
                throw ProjectLifecycleException::archived($lockedProject);
            }

            $locked = Application::query()
                ->whereKey($application->id)
                ->whereBelongsTo($lockedProject)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isArchived()) {
                throw ApplicationLifecycleException::notArchived($locked);
            }

            $locked->archived_at = null;
            $locked->save();

            event(new ApplicationRestored($locked, $context->forOrganization($organization->id)));
        });
    }
}
