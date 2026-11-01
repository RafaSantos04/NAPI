<?php

namespace App\Domain\ApiInventory\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\ProjectTreeLock;
use App\DTOs\CreateApiSpecificationDto;
use App\Events\ApiSpecificationCreated;
use App\Models\ApiSpecification;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class CreateApiSpecification
{
    public static function execute(
        Organization $organization,
        Application $application,
        CreateApiSpecificationDto $dto,
        AuditContext $context,
    ): ApiSpecification {
        return DB::transaction(function () use ($organization, $application, $dto, $context) {
            $lockedApplication = ProjectTreeLock::activeApplication($organization, $application);

            $specification = $lockedApplication->apiSpecifications()->make([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
            ]);
            $specification->organization()->associate($organization);
            $specification->save();

            event(new ApiSpecificationCreated($specification, $context->forOrganization($organization->id)));

            return $specification;
        });
    }
}
