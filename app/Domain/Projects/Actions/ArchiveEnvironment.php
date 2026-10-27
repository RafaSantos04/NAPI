<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ApplicationLifecycleException;
use App\Domain\Projects\Exceptions\EnvironmentLifecycleException;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\EnvironmentArchived;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ArchiveEnvironment
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

            // Under an archived ancestor the whole subtree is read-only.
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

            $locked->archived_at = now()->toImmutable();
            $locked->save();

            event(new EnvironmentArchived($locked, $context->forOrganization($organization->id)));
        });
    }
}
