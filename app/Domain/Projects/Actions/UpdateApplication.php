<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\DTOs\UpdateApplicationDto;
use App\Events\ApplicationUpdated;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class UpdateApplication
{
    public static function execute(
        Organization $organization,
        Project $project,
        Application $application,
        UpdateApplicationDto $dto,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $project, $application, $dto, $context) {
            // Ancestor first, then the row: the same order in every write of
            // the tree, so a concurrent archive cannot deadlock with it.
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

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

            $locked->fill($dto->attributes);
            $fields = array_keys($locked->getDirty());

            if ($fields === []) {
                return;
            }

            $locked->save();
            event(new ApplicationUpdated($locked, $fields, $context->forOrganization($organization->id)));
        });
    }
}
