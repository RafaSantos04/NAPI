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
            ->assertJsonPath('data.user.id', $member->id)
            ->json('data.id');

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

    it('returns 404 when a non-owner adds or changes members', function (OrganizationRole $role) {
        $member = User::factory()->create();
        $target = User::factory()->create();
        $organization = organizationFor($member, $role);
        $membership = OrganizationMembership::factory()->for($organization)->for($target)->create();

        asToken($member, 'POST', "/api/v1/organizations/{$organization->id}/members", [
            'user_id' => User::factory()->create()->id,
            'role' => 'member',
        ])->assertNotFound();

        asToken($member, 'PATCH', "/api/v1/organizations/{$organization->id}/members/{$membership->id}", [
            'role' => 'owner',
        ])->assertNotFound();

        expect($membership->fresh()->role)->toBe(OrganizationRole::Member)
            ->and(OrganizationMembership::whereBelongsTo($organization)->count())->toBe(2);
    })->with([
        'member' => [OrganizationRole::Member],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('lets an owner promote a member to manager', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = OrganizationMembership::factory()->for($organization)->create();

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organization->id}/members/{$membership->id}", [
            'role' => 'manager',
        ])->assertOk()->assertJsonPath('data.role', 'manager');

        expect($membership->fresh()->role)->toBe(OrganizationRole::Manager)
            ->and(AuditLog::where('action', 'membership_updated')->sole()->meta)->toEqual([
                'user_id' => $membership->user_id,
                'changes' => ['role' => 'manager'],
            ]);
    });

    it('keeps a membership bound to its organization and user in PostgreSQL', function (string $column) {
        $membership = OrganizationMembership::factory()->create();
        $replacement = $column === 'organization_id'
            ? organizationFor(User::factory()->create())->id
            : User::factory()->create()->id;

        // Savepoint, so the rejected statement does not abort the test transaction.
        expect(fn () => DB::transaction(fn () => DB::table('organization_memberships')
            ->where('id', $membership->id)
            ->update([$column => $replacement])))
            ->toThrow(QueryException::class, "organization_memberships.{$column} is immutable");

        expect($membership->fresh()->{$column})->toBe($membership->{$column});
    })->with(['organization_id', 'user_id']);

    it('still lets PostgreSQL change the role and status of a membership', function () {
        $membership = OrganizationMembership::factory()->create();

        DB::table('organization_memberships')
            ->where('id', $membership->id)
            ->update(['role' => 'manager', 'status' => 'disabled']);

        expect($membership->fresh()->role)->toBe(OrganizationRole::Manager)
            ->and($membership->fresh()->status)->toBe(OrganizationMembershipStatus::Disabled);
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
