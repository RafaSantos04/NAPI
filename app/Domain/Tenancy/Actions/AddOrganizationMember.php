<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\DTOs\AddOrganizationMemberDto;
use App\Events\OrganizationMembershipCreated;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;

class AddOrganizationMember
{
    public static function execute(
        Organization $organization,
        AddOrganizationMemberDto $dto,
        AuditContext $context,
    ): OrganizationMembership {
        return DB::transaction(function () use ($organization, $dto, $context) {
            $membership = OrganizationMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $dto->userId,
                'role' => $dto->role,
                'status' => OrganizationMembershipStatus::Active,
            ]);

            event(new OrganizationMembershipCreated($membership, $context->forOrganization($organization->id)));

            return $membership;
        });
    }
}
