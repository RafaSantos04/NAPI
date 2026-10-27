<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\DTOs\CreateApplicationDto;
use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class CreateApplication
{
    public static function execute(Organization $organization, Project $project, CreateApplicationDto $dto, AuditContext $context): Application
    {
        return DB::transaction(function () use ($organization, $project, $dto, $context) {
            // A shared lock lets writes under the same project run together
            // while a concurrent ArchiveProject (FOR UPDATE) waits for them.
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

            if ($lockedProject->isArchived()) {
                throw ProjectLifecycleException::archived($lockedProject);
            }

            $application = $lockedProject->applications()->make([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
            ]);
            $application->organization()->associate($organization);
            $application->save();

            event(new ApplicationCreated($application, $context->forOrganization($organization->id)));

            return $application;
        });
    }
}
