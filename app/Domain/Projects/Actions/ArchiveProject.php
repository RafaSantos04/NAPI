<?php

namespace App\Domain\Projects\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Exceptions\ProjectLifecycleException;
use App\Events\ProjectArchived;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ArchiveProject
{
    /**
     * Only the project row changes: its tree becomes read-only through the
     * archived ancestor and comes back whole on restore (ADR-0012).
     */
    public static function execute(Organization $organization, Project $project, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $project, $context) {
            $locked = Project::query()
                ->whereKey($project->id)
                ->whereBelongsTo($organization)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw ProjectLifecycleException::archived($locked);
            }

            $locked->archived_at = now()->toImmutable();
            $locked->save();

            event(new ProjectArchived($locked, $context->forOrganization($organization->id)));
        });
    }
}
