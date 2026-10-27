<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\DTOs\UpdateProjectDto;
use App\Events\ProjectUpdated;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    public static function execute(Organization $organization, Project $project, UpdateProjectDto $dto, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $project, $dto, $context) {
            // Serializes with a concurrent archive: the state is read under lock.
            $locked = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw ProjectLifecycleException::archived($locked);
            }

            $locked->fill($dto->attributes);
            $fields = array_keys($locked->getDirty());

            if ($fields === []) {
                return;
            }

            $locked->save();
            event(new ProjectUpdated($locked, $fields, $context->forOrganization($organization->id)));
        });
    }
}
