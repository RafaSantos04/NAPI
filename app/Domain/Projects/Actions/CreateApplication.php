<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
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
            $lockedProject = ProjectTreeLock::activeProject($organization, $project->id);

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
