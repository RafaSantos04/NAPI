<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\ProjectTreeLock;
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
            $lockedProject = ProjectTreeLock::activeProject($organization, $project->id);

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
