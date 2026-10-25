<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\IAM\AuditContext;
use App\DTOs\UpdateOrganizationDto;
use App\Events\OrganizationUpdated;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class UpdateOrganization
{
    public static function execute(Organization $organization, UpdateOrganizationDto $dto, AuditContext $context): void
    {
        DB::transaction(function () use ($organization, $dto, $context) {
            $organization->fill($dto->attributes);
            $fields = array_keys($organization->getDirty());

            if ($fields === []) {
                return;
            }

            $organization->save();
            event(new OrganizationUpdated($organization, $fields, $context->forOrganization($organization->id)));
        });
    }
}
