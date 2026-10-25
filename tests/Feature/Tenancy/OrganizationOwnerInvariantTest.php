<?php

use App\Domain\IAM\Actions\DeactivateUser;
use App\Domain\IAM\Actions\DeleteUser;
use App\Domain\IAM\AuditContext;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Exceptions\LastActiveOrganizationOwnerException;
use App\Models\OrganizationMembership;
use App\Models\User;

describe('Last active organization owner invariant', function () {
    it('does not remove the last active owner', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = $owner->organizationMemberships()->sole();

        asToken($owner, 'DELETE', "/api/v1/organizations/{$organization->id}/members/{$membership->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'The organization must keep at least one active owner.');

        expect($membership->fresh()->status)->toBe(OrganizationMembershipStatus::Active);
    });

    it('does not demote or disable the last active owner', function (array $payload) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = $owner->organizationMemberships()->sole();

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organization->id}/members/{$membership->id}", $payload)
            ->assertStatus(409);

        expect($membership->fresh()->isOwner())->toBeTrue()
            ->and($membership->fresh()->isActive())->toBeTrue();
    })->with([
        'demotion' => [['role' => 'member']],
        'disable' => [['status' => 'disabled']],
    ]);

    it('allows removal when another active owner remains', function () {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = $owner->organizationMemberships()->sole();
        OrganizationMembership::factory()->owner()->for($organization)->for($otherOwner)->create();

        asToken($owner, 'DELETE', "/api/v1/organizations/{$organization->id}/members/{$membership->id}")
            ->assertOk();

        expect($membership->fresh()->status)->toBe(OrganizationMembershipStatus::Disabled);
    });

    it('blocks global deactivation and deletion of the only owner', function (string $action) {
        $owner = User::factory()->create();
        organizationFor($owner);

        expect(fn () => $action === 'deactivate'
            ? DeactivateUser::execute($owner, AuditContext::system())
            : DeleteUser::execute($owner, AuditContext::system()))
            ->toThrow(LastActiveOrganizationOwnerException::class);

        expect($owner->fresh()->is_active)->toBeTrue()
            ->and($owner->fresh()->deleted_at)->toBeNull();
    })->with(['deactivate', 'delete']);

    it('allows global deactivation when another valid owner remains', function () {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->owner()->for($organization)->for($otherOwner)->create();

        DeactivateUser::execute($owner, AuditContext::system());

        expect($owner->fresh()->is_active)->toBeFalse();
    });
});
