<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\CreateProjectDto;
use App\Events\ProjectCreated;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public static function execute(Organization $organization, CreateProjectDto $dto, User $actor, AuditContext $context): Project
    {
        return DB::transaction(function () use ($organization, $dto, $actor, $context) {
            // The permission layer already required an active membership;
            // it becomes the creator that `archive_own` is checked against.
            $creator = $actor->organizationMemberships()->whereBelongsTo($organization)->firstOrFail();

            $project = $organization->projects()->make([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
            ]);
            $project->createdBy()->associate($creator);
            $project->save();

            event(new ProjectCreated($project, $context->forOrganization($organization->id)));

            return $project;
        });
    }
}
