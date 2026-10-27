<?php

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;

describe('Creating projects', function () {
    it('creates a project with the member as its creator and audits it', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $membership = $member->organizationMemberships()->sole();

        $id = asToken($member, 'POST', "/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Billing API',
            'slug' => 'billing-api',
            'description' => 'Invoices and payments',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'billing-api')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.created_by_membership_id', $membership->id)
            ->json('data.id');

        $project = Project::findOrFail($id);
        $audit = AuditLog::where('action', 'project_created')->sole();

        expect($project->organization_id)->toBe($organization->id)
            ->and($project->created_by_membership_id)->toBe($membership->id)
            ->and($project->archived_at)->toBeNull();
        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->user_id)->toBe($member->id)
            ->and($audit->subject_id)->toBe($id);
    });

    it('ignores tenant and creator fields sent in the payload', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $foreignMembership = OrganizationMembership::factory()->create();

        $id = asToken($member, 'POST', "/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Billing API',
            'slug' => 'billing-api',
            'organization_id' => $foreignMembership->organization_id,
            'created_by_membership_id' => $foreignMembership->id,
        ])->assertCreated()->json('data.id');

        expect(Project::findOrFail($id)->organization_id)->toBe($organization->id)
            ->and(Project::findOrFail($id)->created_by_membership_id)->toBe($member->organizationMemberships()->sole()->id);
    });

    it('rejects invalid project data with 422', function (array $payload, string $field) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        projectCreatedBy($owner, $organization, ['slug' => 'taken']);

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Valid name',
            'slug' => 'valid-slug',
            ...$payload,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Project::count())->toBe(1);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'malformed slug' => [['slug' => 'Not Valid'], 'slug'],
        'slug taken in the organization' => [['slug' => 'taken'], 'slug'],
        'description too long' => [['description' => str_repeat('a', 2001)], 'description'],
    ]);

    it('accepts a slug already used by another organization', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        Project::factory()->create(['slug' => 'billing-api']);

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Billing API',
            'slug' => 'billing-api',
        ])->assertCreated();

        expect(Project::where('slug', 'billing-api')->count())->toBe(2);
    });

    it('does not keep the project when its audit entry fails', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        AuditLog::creating(fn () => throw new RuntimeException('Audit storage unavailable.'));

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects", [
            'name' => 'Billing API',
            'slug' => 'billing-api',
        ])->assertServerError();

        expect(Project::count())->toBe(0);
    });
});

describe('Listing and viewing projects', function () {
    it('lists only the active projects of the organization by default', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $active = projectCreatedBy($member, $organization, ['name' => 'Active']);
        projectCreatedBy($member, $organization, ['name' => 'Archived', 'archived_at' => now()]);
        Project::factory()->create(['name' => 'Other tenant']);

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/projects")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.total', 1);
    });

    it('filters projects by status', function (string $status, array $expectedNames) {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        projectCreatedBy($member, $organization, ['name' => 'Active']);
        projectCreatedBy($member, $organization, ['name' => 'Archived', 'archived_at' => now()]);

        $names = asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/projects?status={$status}")
            ->assertOk()
            ->json('data.*.name');

        expect($names)->toBe($expectedNames);
    })->with([
        'archived' => ['archived', ['Archived']],
        'all' => ['all', ['Active', 'Archived']],
    ]);

    it('rejects an unknown status filter with 422', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/projects?status=deleted")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('searches names case-insensitively and matches wildcards literally', function (string $search, array $expectedNames) {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        projectCreatedBy($member, $organization, ['name' => 'Billing API']);
        projectCreatedBy($member, $organization, ['name' => '100% Uptime']);
        projectCreatedBy($member, $organization, ['name' => 'Payments_v2']);

        $names = asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/projects?".http_build_query(['search' => $search]))
            ->assertOk()
            ->json('data.*.name');

        expect($names)->toBe($expectedNames);
    })->with([
        'case-insensitive' => ['billing', ['Billing API']],
        'percent sign' => ['%', ['100% Uptime']],
        'underscore' => ['_', ['Payments_v2']],
    ]);

    it('shows a project to a member of its organization', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);

        asToken($member, 'GET', "/api/v1/organizations/{$organization->id}/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id);
    });

    it('returns 404 for a project of another organization under the own organization', function (string $method, string $suffix) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $foreignProject = Project::factory()->create(['name' => 'Foreign']);

        asToken($owner, $method, "/api/v1/organizations/{$organization->id}/projects/{$foreignProject->id}{$suffix}", [
            'name' => 'Hijacked',
        ])->assertNotFound();

        expect($foreignProject->fresh()->name)->toBe('Foreign')
            ->and($foreignProject->fresh()->archived_at)->toBeNull();
    })->with([
        'show' => ['GET', ''],
        'update' => ['PATCH', ''],
        'archive' => ['POST', '/archive'],
    ]);

    it('returns 404 to users without an active membership', function (string $actor) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $user = match ($actor) {
            'outsider' => User::factory()->create(),
            'disabled member' => tap(User::factory()->create(), fn (User $user) => OrganizationMembership::factory()
                ->disabled()->for($organization)->for($user)->create()),
            'global admin' => (function () {
                $this->seed();

                return seededUser('admin');
            })->call($this),
        };

        asToken($user, 'GET', "/api/v1/organizations/{$organization->id}/projects")->assertNotFound();
        asToken($user, 'GET', "/api/v1/organizations/{$organization->id}/projects/{$project->id}")->assertNotFound();
    })->with(['outsider', 'disabled member', 'global admin']);
});

describe('Updating projects', function () {
    it('updates a project and audits only the changed fields', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization, ['name' => 'Old name']);

        asToken($member, 'PATCH', "/api/v1/organizations/{$organization->id}/projects/{$project->id}", [
            'name' => 'New name',
            'slug' => $project->slug,
        ])->assertOk()->assertJsonPath('data.name', 'New name');

        expect($project->fresh()->name)->toBe('New name')
            ->and(AuditLog::where('action', 'project_updated')->sole()->meta)->toBe(['fields' => ['name']]);
    });

    it('returns 409 when updating an archived project', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization, ['name' => 'Frozen', 'archived_at' => now()]);

        asToken($owner, 'PATCH', "/api/v1/organizations/{$organization->id}/projects/{$project->id}", [
            'name' => 'Changed',
        ])->assertConflict()->assertJsonPath('message', 'The project is archived.');

        expect($project->fresh()->name)->toBe('Frozen')
            ->and(AuditLog::where('action', 'project_updated')->exists())->toBeFalse();
    });
});

describe('Archiving projects', function () {
    it('lets a member archive and restore a project it created', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);
        $url = "/api/v1/organizations/{$organization->id}/projects/{$project->id}";

        asToken($member, 'POST', "{$url}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        expect($project->fresh()->archived_at)->not->toBeNull();

        asToken($member, 'POST', "{$url}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        expect($project->fresh()->archived_at)->toBeNull()
            ->and(AuditLog::where('subject_id', $project->id)->orderBy('created_at')->pluck('action')->all())
            ->toBe(['project_archived', 'project_restored']);
    });

    it('returns 403 when a member archives or restores a project created by someone else', function (string $operation) {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->for($organization)->for($member)->create();
        $project = projectCreatedBy($owner, $organization, ['archived_at' => $operation === 'restore' ? now() : null]);

        asToken($member, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$project->id}/{$operation}")
            ->assertForbidden();

        expect($project->fresh()->isArchived())->toBe($operation === 'restore')
            ->and(AuditLog::where('subject_id', $project->id)->exists())->toBeFalse();
    })->with(['archive', 'restore']);

    it('lets owners and managers archive any project of the organization', function (OrganizationRole $role) {
        $creator = User::factory()->create();
        $actor = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        OrganizationMembership::factory()->for($organization)->for($actor)->create(['role' => $role]);
        $project = projectCreatedBy($creator, $organization);

        asToken($actor, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$project->id}/archive")
            ->assertOk();

        expect($project->fresh()->isArchived())->toBeTrue();
    })->with([
        'owner' => [OrganizationRole::Owner],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('returns 409 for a transition that does not apply', function (string $operation, bool $archived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization, ['archived_at' => $archived ? now() : null]);

        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$project->id}/{$operation}")
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($project->fresh()->isArchived())->toBe($archived)
            ->and(AuditLog::where('subject_id', $project->id)->exists())->toBeFalse();
    })->with([
        'archive an archived project' => ['archive', true, 'The project is archived.'],
        'restore an active project' => ['restore', false, 'The project is not archived.'],
    ]);

    it('returns 404 when the creator has lost its membership', function () {
        $owner = User::factory()->create();
        $creator = User::factory()->create();
        $organization = organizationFor($owner);
        $membership = OrganizationMembership::factory()->for($organization)->for($creator)->create();
        $project = Project::factory()->createdBy($membership)->create();
        $membership->update(['status' => OrganizationMembershipStatus::Disabled]);

        asToken($creator, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$project->id}/archive")
            ->assertNotFound();

        expect($project->fresh()->isArchived())->toBeFalse();
    });
});
