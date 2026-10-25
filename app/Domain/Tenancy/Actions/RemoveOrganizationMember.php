<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\EnsureActiveOrganizationOwnerRemains;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Events\OrganizationMembershipRemoved;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;

class RemoveOrganizationMember
{
    public static function execute(
        Organization $organization,
        OrganizationMembership $membership,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $membership, $context) {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $lockedMembership = OrganizationMembership::query()
                ->whereKey($membership->id)
                ->whereBelongsTo($organization)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMembership->isActive()) {
                return;
            }

            EnsureActiveOrganizationOwnerRemains::beforeChanging(
                $lockedMembership,
                $lockedMembership->role,
                OrganizationMembershipStatus::Disabled,
            );

            $lockedMembership->update(['status' => OrganizationMembershipStatus::Disabled]);
            event(new OrganizationMembershipRemoved(
                $lockedMembership,
                $context->forOrganization($organization->id),
            ));
        });
    }
}
