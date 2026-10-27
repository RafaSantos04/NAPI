<?php

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('Organizations', function () {
    it('creates an organization with its creator as the active owner', function () {
        $owner = User::factory()->create();

        $id = asToken($owner, 'POST', '/api/v1/organizations', [
            'name' => 'ACME Software',
            'slug' => 'acme-software',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'ACME Software')
            ->json('data.id');

        $organization = Organization::findOrFail($id);
        $membership = OrganizationMembership::whereBelongsTo($organization)->sole();

        expect($membership->user_id)->toBe($owner->id)
            ->and($membership->role)->toBe(OrganizationRole::Owner)
            ->and($membership->status)->toBe(OrganizationMembershipStatus::Active)
            ->and(AuditLog::where('organization_id', $id)->count())->toBe(2)
            ->and(AuditLog::where('action', 'organization_created')->value('user_id'))->toBe($owner->id);
    });

    it('requires authentication and a write-capable token to create', function () {
        $payload = ['name' => 'ACME', 'slug' => 'acme'];

        $this->postJson('/api/v1/organizations', $payload)->assertUnauthorized();
        asToken(User::factory()->create(), 'POST', '/api/v1/organizations', $payload, ['read'])
            ->assertForbidden();

        expect(Organization::count())->toBe(0);
    });

    it('lists only organizations with an active membership', function () {
        $user = User::factory()->create();
        $visible = organizationFor($user);
        organizationFor($user, status: OrganizationMembershipStatus::Disabled);
        Organization::factory()->create();

        asToken($user, 'GET', '/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.membership_role', 'owner');
    });

    it('lets a non-owner view but returns 404 on update', function (OrganizationRole $role) {
        $member = User::factory()->create();
        $organization = organizationFor($member, $role);

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id);

        asToken($member, 'PATCH', "/api/v1/organizations/{$organization->id}", ['name' => 'Changed'])
            ->assertNotFound();

        expect($organization->fresh()->name)->not->toBe('Changed');
    })->with([
        'member' => [OrganizationRole::Member],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('lets an owner update the organization and audits only changed fields', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organization->id}", [
            'name' => 'New name',
        ])->assertOk()->assertJsonPath('data.name', 'New name');

        $audit = AuditLog::where('action', 'organization_updated')->sole();

        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->meta)->toBe(['fields' => ['name']]);
    });

    it('rejects duplicate and malformed slugs', function () {
        Organization::factory()->create(['slug' => 'already-used']);
        $user = User::factory()->create();

        asToken($user, 'POST', '/api/v1/organizations', [
            'name' => 'Duplicate',
            'slug' => 'already-used',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');

        asToken($user, 'POST', '/api/v1/organizations', [
            'name' => 'Malformed',
            'slug' => 'Not Valid',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
    });
});

describe('Append-only audit trail', function () {
    it('rejects mutation through an AuditLog model', function () {
        $owner = User::factory()->create();
        asToken($owner, 'POST', '/api/v1/organizations', ['name' => 'ACME', 'slug' => 'acme'])->assertCreated();
        $audit = AuditLog::firstOrFail();

        expect(fn () => $audit->update(['action' => 'tampered']))
            ->toThrow(LogicException::class, 'Audit logs are append-only.');
        expect(fn () => $audit->delete())
            ->toThrow(LogicException::class, 'Audit logs are append-only.');
    });

    it('rejects mutation that bypasses the model', function () {
        $owner = User::factory()->create();
        asToken($owner, 'POST', '/api/v1/organizations', ['name' => 'ACME', 'slug' => 'acme'])->assertCreated();

        expect(fn () => DB::table('audit_logs')->update(['action' => 'tampered']))
            ->toThrow(QueryException::class);
    });

    it('rejects deletion that bypasses the model', function () {
        $owner = User::factory()->create();
        asToken($owner, 'POST', '/api/v1/organizations', ['name' => 'ACME', 'slug' => 'acme'])->assertCreated();

        expect(fn () => DB::table('audit_logs')->delete())
            ->toThrow(QueryException::class);
    });
});
