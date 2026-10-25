<?php

use App\Models\OrganizationMembership;
use App\Models\User;

describe('Tenant isolation', function () {
    it('denies an authenticated user who substitutes another organization id', function () {
        $actor = User::factory()->create();
        organizationFor($actor);
        $foreignOrganization = organizationFor(User::factory()->create());

        asToken($actor, 'GET', "/api/v1/organizations/{$foreignOrganization->id}")
            ->assertNotFound();
        asToken($actor, 'PATCH', "/api/v1/organizations/{$foreignOrganization->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
        asToken($actor, 'GET', "/api/v1/organizations/{$foreignOrganization->id}/members")
            ->assertNotFound();

        expect($foreignOrganization->fresh()->name)->not->toBe('Hijacked');
    });

    it('denies a valid token and payload when the requested tenant is foreign', function () {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $foreignOrganization = organizationFor(User::factory()->create());

        asToken($actor, 'POST', "/api/v1/organizations/{$foreignOrganization->id}/members", [
            'user_id' => $target->id,
            'role' => 'owner',
        ])->assertNotFound();

        expect(OrganizationMembership::where('user_id', $target->id)->exists())->toBeFalse();
    });

    it('scopes nested membership binding to the organization in the URL', function () {
        $owner = User::factory()->create();
        $organizationA = organizationFor($owner);
        $organizationB = organizationFor($owner);
        $foreignMembership = OrganizationMembership::factory()
            ->for($organizationB)
            ->for(User::factory()->create())
            ->create();

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organizationA->id}/members/{$foreignMembership->id}", [
            'role' => 'owner',
        ])->assertNotFound();

        expect($foreignMembership->fresh()->role->value)->toBe('member');
    });

    it('switches organization by route and invalidates a disabled context', function () {
        $user = User::factory()->create();
        $organizationA = organizationFor($user);
        $organizationB = organizationFor($user);

        asToken($user, 'GET', "/api/v1/organizations/{$organizationA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $organizationA->id);
        asToken($user, 'GET', "/api/v1/organizations/{$organizationB->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $organizationB->id);

        $user->organizationMemberships()
            ->where('organization_id', $organizationB->id)
            ->update(['status' => 'disabled']);

        asToken($user, 'GET', "/api/v1/organizations/{$organizationB->id}")
            ->assertNotFound();
    });

    it('does not let the global administrator bypass organization membership', function () {
        $this->seed();
        $organization = organizationFor(User::factory()->create());

        asToken(seededUser('admin'), 'GET', "/api/v1/organizations/{$organization->id}")
            ->assertNotFound();
    });
});
