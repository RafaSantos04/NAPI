<?php

namespace App\Domain\ApiInventory\Actions;

use App\Domain\ApiInventory\Exceptions\ApiSpecificationLifecycleException;
use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\DTOs\UpdateApiSpecificationDto;
use App\Events\ApiSpecificationUpdated;
use App\Models\ApiSpecification;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class UpdateApiSpecification
{
    public static function execute(
        Organization $organization,
        ApiSpecification $specification,
        UpdateApiSpecificationDto $dto,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $specification, $dto, $context) {
            // The shallow route has no ancestors; the tree lock walks up from
            // the specification's own application.
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $specification->application);

            $locked = ApiSpecification::query()
                ->whereKey($specification->id)
                ->whereBelongsTo($lockedApplication)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                throw ApiSpecificationLifecycleException::archived($locked);
            }

            $locked->fill($dto->attributes);
            $fields = array_keys($locked->getDirty());

            if ($fields === []) {
                return;
            }

            $locked->save();
            event(new ApiSpecificationUpdated($locked, $fields, $context->forOrganization($organization->id)));
        });
    }
}
