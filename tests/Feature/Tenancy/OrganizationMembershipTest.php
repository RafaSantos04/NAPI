<?php

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('Organization memberships', function () {
    it('lets an owner add an existing active user', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);

        $id = asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $member->id,
            'role' => 'member',
        ])->assertCreated()
            ->assertJsonPath('user.id', $member->id)
            ->json('id');

        $membership = OrganizationMembership::findOrFail($id);
        expect($membership->status)->toBe(OrganizationMembershipStatus::Active)
            ->and($membership->role)->toBe(OrganizationRole::Member);

        $audit = AuditLog::where('action', 'membership_created')->latest('created_at')->firstOrFail();
        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->user_id)->toBe($owner->id);
    });

    it('rejects duplicate memberships in validation and in PostgreSQL', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $owner->id,
            'role' => 'member',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        expect(fn () => OrganizationMembership::factory()->for($organization)->for($owner)->create())
            ->toThrow(QueryException::class);
    });

    it('does not create a partial invite for an inactive or unknown user', function () {
        $owner = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $organization = organizationFor($owner);

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => $inactive->id,
            'role' => 'member',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'role' => 'member',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    });

    it('does not let a regular member add or change members', function () {
        $member = User::factory()->create();
        $target = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $membership = OrganizationMembership::factory()->for($organization)->for($target)->create();

        asToken($member, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => User::factory()->create()->id,
            'role' => 'member',
        ])->assertNotFound();

        asToken($member, 'PATCH', "/api/v1/organizations/{$organization->id}/members/{$membership->id}", [
            'role' => 'owner',
        ])->assertNotFound();
    });

    it('lets members view the roster without granting management', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $member->id);
    });

    it('disables a membership without deleting its history and revokes tenant access immediately', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = OrganizationMembership::factory()->for($organization)->for($member)->create();

        asToken($owner, 'DELETE', "/api/v1/organizations/{$organization->id}/members/{$membership->id}")
            ->assertOk();

        expect($membership->fresh()->status)->toBe(OrganizationMembershipStatus::Disabled)
            ->and(OrganizationMembership::whereKey($membership->id)->exists())->toBeTrue();

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}")->assertNotFound();
        expect(AuditLog::where('action', 'membership_removed')->where('organization_id', $organization->id)->count())
            ->toBe(1);
    });

    it('reactivates the same membership through its lifecycle', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = OrganizationMembership::factory()->disabled()->for($organization)->for($member)->create();

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organization->id}/members/{$membership->id}", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}")->assertOk();
    });

    it('enforces the closed role and status sets in PostgreSQL', function (string $column, string $value) {
        $organization = organizationFor(User::factory()->create());

        expect(fn () => DB::table('organization_memberships')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'role' => $column === 'role' ? $value : 'member',
            'status' => $column === 'status' ? $value : 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    })->with([
        'unknown role' => ['role', 'administrator'],
        'unknown status' => ['status', 'invited'],
    ]);
});
