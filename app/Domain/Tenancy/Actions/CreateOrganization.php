<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\DTOs\CreateOrganizationDto;
use App\Events\OrganizationCreated;
use App\Events\OrganizationMembershipCreated;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public static function execute(CreateOrganizationDto $dto, User $owner, AuditContext $context): Organization
    {
        return DB::transaction(function () use ($dto, $owner, $context) {
            $organization = Organization::create([
                'name' => $dto->name,
                'slug' => $dto->slug,
            ]);

            $membership = OrganizationMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'role' => OrganizationRole::Owner,
                'status' => OrganizationMembershipStatus::Active,
            ]);

            $organizationContext = $context->forOrganization($organization->id);
            event(new OrganizationCreated($organization, $organizationContext));
            event(new OrganizationMembershipCreated($membership, $organizationContext));

            return $organization;
        });
    }
}
