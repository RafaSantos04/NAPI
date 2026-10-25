<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Exceptions\LastActiveOrganizationOwnerException;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class EnsureActiveOrganizationOwnerRemains
{
    public static function beforeChanging(
        OrganizationMembership $membership,
        OrganizationRole $newRole,
        OrganizationMembershipStatus $newStatus,
    ): void {
        self::requireTransaction();

        $organization = Organization::whereKey($membership->organization_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! self::isValidOwner($membership)) {
            return;
        }

        $userRemainsValid = User::query()
            ->whereKey($membership->user_id)
            ->where('is_active', true)
            ->exists();

        if ($newRole === OrganizationRole::Owner
            && $newStatus === OrganizationMembershipStatus::Active
            && $userRemainsValid) {
            return;
        }

        self::ensureAnotherOwnerExists($organization, membershipId: $membership->id);
    }

    public static function beforeRemovingUser(User $user): void
    {
        self::requireTransaction();

        $organizationIds = OrganizationMembership::query()
            ->whereBelongsTo($user)
            ->where('role', OrganizationRole::Owner)
            ->where('status', OrganizationMembershipStatus::Active)
            ->orderBy('organization_id')
            ->pluck('organization_id');

        if ($organizationIds->isEmpty()) {
            return;
        }

        $organizations = Organization::query()
            ->whereKey($organizationIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($organizations as $organization) {
            self::ensureAnotherOwnerExists($organization, userId: $user->id);
        }
    }

    private static function isValidOwner(OrganizationMembership $membership): bool
    {
        return OrganizationMembership::query()
            ->whereKey($membership->id)
            ->where('role', OrganizationRole::Owner)
            ->where('status', OrganizationMembershipStatus::Active)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    private static function ensureAnotherOwnerExists(
        Organization $organization,
        ?string $membershipId = null,
        ?string $userId = null,
    ): void {
        $othersRemain = OrganizationMembership::query()
            ->whereBelongsTo($organization)
            ->where('role', OrganizationRole::Owner)
            ->where('status', OrganizationMembershipStatus::Active)
            ->when($membershipId, fn ($query) => $query->whereKeyNot($membershipId))
            ->when($userId, fn ($query) => $query->where('user_id', '!=', $userId))
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->exists();

        if (! $othersRemain) {
            throw new LastActiveOrganizationOwnerException($organization);
        }
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The organization owner invariant must be checked inside a transaction.');
        }
    }
}
