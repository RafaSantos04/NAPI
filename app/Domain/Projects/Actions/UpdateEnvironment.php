<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\EnvironmentLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\DTOs\UpdateEnvironmentDto;
use App\Events\EnvironmentUpdated;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEnvironment
{
    public static function execute(
        Organization $organization,
        Project $project,
        Application $application,
        Environment $environment,
        UpdateEnvironmentDto $dto,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $project, $application, $environment, $dto, $context) {
            $lockedProject = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->sharedLock()
                ->firstOrFail();

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

            if ($locked->isArchived()) {
                throw EnvironmentLifecycleException::archived($locked);
            }

            $locked->fill($dto->attributes);

            if ($dto->responsibleMembershipId !== null) {
                $responsible = OrganizationMembership::query()
                    ->whereKey($dto->responsibleMembershipId)
                    ->whereBelongsTo($organization)
                    ->where('status', OrganizationMembershipStatus::Active)
                    ->sharedLock()
                    ->first();

                if ($responsible === null) {
                    throw ValidationException::withMessages([
                        'responsible_membership_id' => 'The responsible membership must be active in this organization.',
                    ]);
                }

                $locked->responsible()->associate($responsible);
            }

            // Field names only: the authorized scope itself stays out of the audit trail.
            $fields = array_keys($locked->getDirty());

            if ($fields === []) {
                return;
            }

            $locked->save();
            event(new EnvironmentUpdated($locked, $fields, $context->forOrganization($organization->id)));
        });
    }
}
