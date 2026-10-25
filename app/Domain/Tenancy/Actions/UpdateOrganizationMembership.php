<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\EnsureActiveOrganizationOwnerRemains;
use App\DTOs\UpdateOrganizationMembershipDto;
use App\Events\OrganizationMembershipUpdated;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use BackedEnum;
use Illuminate\Support\Facades\DB;

class UpdateOrganizationMembership
{
    public static function execute(
        Organization $organization,
        OrganizationMembership $membership,
        UpdateOrganizationMembershipDto $dto,
        AuditContext $context,
    ): void {
        DB::transaction(function () use ($organization, $membership, $dto, $context) {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $lockedMembership = OrganizationMembership::query()
                ->whereKey($membership->id)
                ->whereBelongsTo($organization)
                ->lockForUpdate()
                ->firstOrFail();

            $newRole = $dto->role ?? $lockedMembership->role;
            $newStatus = $dto->status ?? $lockedMembership->status;

            EnsureActiveOrganizationOwnerRemains::beforeChanging($lockedMembership, $newRole, $newStatus);

            $lockedMembership->fill([
                'role' => $newRole,
                'status' => $newStatus,
            ]);
            $changes = $lockedMembership->getDirty();

            if ($changes === []) {
                return;
            }

            $lockedMembership->save();
            event(new OrganizationMembershipUpdated(
                $lockedMembership,
                array_map(fn (mixed $value) => $value instanceof BackedEnum ? $value->value : $value, $changes),
                $context->forOrganization($organization->id),
            ));
        });
    }
}
