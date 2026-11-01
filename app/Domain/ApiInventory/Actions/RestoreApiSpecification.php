<?php

namespace App\Domain\ApiInventory\Actions;

use App\Domain\ApiInventory\Exceptions\ApiSpecificationLifecycleException;
use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\Events\ApiSpecificationRestored;
use App\Models\ApiSpecification;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class RestoreApiSpecification
{
    public static function execute(Organization $organization, ApiSpecification $specification, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $specification, $context) {
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $specification->application);

            $locked = ApiSpecification::query()
                ->whereKey($specification->id)
                ->whereBelongsTo($lockedApplication)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isArchived()) {
                throw ApiSpecificationLifecycleException::notArchived($locked);
            }

            $locked->archived_at = null;
            $locked->save();

            event(new ApiSpecificationRestored($locked, $context->forOrganization($organization->id)));
        });
    }
}
