<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\ProjectRestored;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class RestoreProject
{
    public static function execute(Organization $organization, Project $project, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $project, $context) {
            $locked = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isArchived()) {
                throw ProjectLifecycleException::notArchived($locked);
            }

            $locked->archived_at = null;
            $locked->save();

            event(new ProjectRestored($locked, $context->forOrganization($organization->id)));
        });
    }
}
