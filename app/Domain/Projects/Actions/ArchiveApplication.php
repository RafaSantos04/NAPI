<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\ApplicationArchived;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ArchiveApplication
{
    public static function execute(Organization $organization, Project $project, Application $application, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $project, $application, $context) {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

            // Under an archived project the whole tree is read-only.
            if ($lockedProject->isArchived()) {
                throw ProjectLifecycleException::archived($lockedProject);
            }

            $locked = Application::query()
                ->whereKey($application->id)
                ->whereBelongsTo($lockedProject)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw ApplicationLifecycleException::archived($locked);
            }

            $locked->archived_at = now()->toImmutable();
            $locked->save();

            event(new ApplicationArchived($locked, $context->forOrganization($organization->id)));
        });
    }
}
