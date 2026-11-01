<?php

namespace App\Domain\ApiInventory\Actions;

use App\Domain\ApiInventory\Exceptions\ApiSpecificationLifecycleException;
use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\Events\ApiSpecificationArchived;
use App\Models\ApiSpecification;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class ArchiveApiSpecification
{
    public static function execute(Organization $organization, ApiSpecification $specification, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $specification, $context) {
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $specification->application);

            // FOR UPDATE also serializes archiving against imports (ADR-0014,
            // decision 17).
            $locked = ApiSpecification::query()
                ->whereKey($specification->id)
                ->whereBelongsTo($lockedApplication)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw ApiSpecificationLifecycleException::archived($locked);
            }

            $locked->archived_at = now()->toImmutable();
            $locked->save();

            event(new ApiSpecificationArchived($locked, $context->forOrganization($organization->id)));
        });
    }
}
