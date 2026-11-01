<?php

use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\ApiSpecification;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// P3-T01 (ADR-0014): listing and creating are nested under the application;
// the other routes are shallow, under the organization.

function specificationsUrl(Application $application): string
{
    return "/api/v1/organizations/{$application->organization_id}/projects/{$application->project_id}/applications/{$application->id}/api-specifications";
}

function specificationUrl(ApiSpecification $specification, string $suffix = '', ?Organization $organization = null): string
{
    $organizationId = $organization->id ?? $specification->organization_id;

    return "/api/v1/organizations/{$organizationId}/api-specifications/{$specification->id}{$suffix}";
}

/**
 * @param  array<string, mixed>  $projectAttributes
 * @param  array<string, mixed>  $applicationAttributes
 */
function applicationOfProjectCreatedBy(User $user, Organization $organization, array $projectAttributes = [], array $applicationAttributes = []): Application
{
    return Application::factory()
        ->for(projectCreatedBy($user, $organization, $projectAttributes))
        ->create($applicationAttributes);
}

describe('Creating API specifications', function () {
    it('creates a specification in the application and audits it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->for($organization)->for($member)->create();
        $application = applicationOfProjectCreatedBy($owner, $organization);

        $id = asToken($member, 'POST', specificationsUrl($application), [
            'name' => 'Public API',
            'slug' => 'public-api',
        ])->assertCreated()
            ->assertJsonPath('data.application_id', $application->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $audit = AuditLog::where('action', 'api_specification_created')->sole();

        expect(ApiSpecification::findOrFail($id)->application_id)->toBe($application->id);
        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->user_id)->toBe($member->id)
            ->and($audit->subject_id)->toBe($id);
    });

    it('ignores tenant and application fields sent in the payload', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationOfProjectCreatedBy($owner, $organization);
        $sibling = applicationOfProjectCreatedBy($owner, $organization);
        $foreignApplication = Application::factory()->create();

        $id = asToken($owner, 'POST', specificationsUrl($application), [
            'name' => 'Public API',
            'slug' => 'public-api',
            'organization_id' => $foreignApplication->organization_id,
            'application_id' => $sibling->id,
        ])->assertCreated()->json('data.id');

        expect(ApiSpecification::findOrFail($id)->application_id)->toBe($application->id)
            ->and(ApiSpecification::findOrFail($id)->organization_id)->toBe($organization->id);
    });

    it('rejects invalid specification data with 422', function (array $payload, string $field) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationOfProjectCreatedBy($owner, $organization);
        ApiSpecification::factory()->for($application)->create(['slug' => 'taken']);

        asToken($owner, 'POST', specificationsUrl($application), [
            'name' => 'Valid name',
            'slug' => 'valid-slug',
            ...$payload,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(ApiSpecification::count())->toBe(1);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'malformed slug' => [['slug' => 'Not Valid'], 'slug'],
        'slug with a trailing hyphen' => [['slug' => 'public-'], 'slug'],
        'slug taken in the application' => [['slug' => 'taken'], 'slug'],
        'oversized description' => [['description' => str_repeat('a', 2001)], 'description'],
    ]);

    it('accepts a slug already used by another application of the organization', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationOfProjectCreatedBy($owner, $organization);
        ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, $organization))
            ->create(['slug' => 'public-api']);

        asToken($owner, 'POST', specificationsUrl($application), [
            'name' => 'Public API',
            'slug' => 'public-api',
        ])->assertCreated();

        expect(ApiSpecification::where('slug', 'public-api')->count())->toBe(2);
    });

    it('returns 409 when creating under an archived ancestor', function (bool $projectArchived, bool $applicationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationOfProjectCreatedBy(
            $owner,
            $organization,
            ['archived_at' => $projectArchived ? now() : null],
            ['archived_at' => $applicationArchived ? now() : null],
        );

        asToken($owner, 'POST', specificationsUrl($application), [
            'name' => 'Public API',
            'slug' => 'public-api',
        ])->assertConflict()->assertJsonPath('message', $message);

        expect(ApiSpecification::count())->toBe(0)
            ->and(AuditLog::where('action', 'api_specification_created')->exists())->toBeFalse();
    })->with([
        'archived application' => [false, true, 'The application is archived.'],
        'archived project' => [true, false, 'The project is archived.'],
    ]);
});

describe('Listing and viewing API specifications', function () {
    it('lists only the active specifications of the application by default', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationOfProjectCreatedBy($member, $organization);
        $active = ApiSpecification::factory()->for($application)->create(['name' => 'Active']);
        ApiSpecification::factory()->for($application)->archived()->create(['name' => 'Archived']);
        ApiSpecification::factory()->for(applicationOfProjectCreatedBy($member, $organization))->create(['name' => 'Sibling']);

        asToken($member, 'GET', specificationsUrl($application))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.total', 1);
    });

    it('filters specifications by status and by name, matching wildcards literally', function (string $query, array $expected) {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationOfProjectCreatedBy($member, $organization);
        ApiSpecification::factory()->for($application)->create(['name' => 'Public API']);
        ApiSpecification::factory()->for($application)->archived()->create(['name' => 'Legacy public API']);
        ApiSpecification::factory()->for($application)->create(['name' => 'Partners 100% API']);

        $names = asToken($member, 'GET', specificationsUrl($application).$query)
            ->assertOk()
            ->json('data.*.name');

        expect($names)->toBe($expected);
    })->with([
        'all statuses, by name' => ['?status=all&search=PUBLIC', ['Legacy public API', 'Public API']],
        'archived only' => ['?status=archived', ['Legacy public API']],
        'a literal percent sign' => ['?search=100%25', ['Partners 100% API']],
        'a literal underscore' => ['?search=_', []],
    ]);

    it('rejects an unknown status filter with 422', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationOfProjectCreatedBy($member, $organization);

        asToken($member, 'GET', specificationsUrl($application).'?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('keeps the specifications under archived ancestors readable', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationOfProjectCreatedBy($member, $organization, ['archived_at' => now()], ['archived_at' => now()]);
        $specification = ApiSpecification::factory()->for($application)->create();

        asToken($member, 'GET', specificationsUrl($application))->assertOk()->assertJsonCount(1, 'data');
        asToken($member, 'GET', specificationUrl($specification))
            ->assertOk()
            ->assertJsonPath('data.id', $specification->id);
    });

    it('returns 404 on the shallow routes for a specification of another organization of the same user', function (string $method, string $suffix) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $foreign = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, organizationFor($owner)))
            ->create(['name' => 'Foreign']);

        asToken($owner, $method, specificationUrl($foreign, $suffix, $organization), ['name' => 'Hijacked'])
            ->assertNotFound();

        expect($foreign->fresh()->name)->toBe('Foreign')
            ->and($foreign->fresh()->archived_at)->toBeNull();
    })->with([
        'show' => ['GET', ''],
        'update' => ['PATCH', ''],
        'archive' => ['POST', '/archive'],
    ]);

    it('denies a specification of another organization without relying on the route binding', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $foreign = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, organizationFor($owner)))
            ->create();
        $gate = Gate::forUser($owner);

        expect($gate->allows('view', [$foreign, $organization]))->toBeFalse()
            ->and($gate->allows('update', [$foreign, $organization]))->toBeFalse()
            ->and($gate->allows('archive', [$foreign, $organization]))->toBeFalse()
            ->and($gate->allows('view', [$foreign, $foreign->organization]))->toBeTrue();
    });

    it('returns 404 for the specifications of an application of another project', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $siblingApplication = applicationOfProjectCreatedBy($owner, $organization);
        $uri = "/api/v1/organizations/{$organization->id}/projects/{$project->id}/applications/{$siblingApplication->id}/api-specifications";

        asToken($owner, 'GET', $uri)->assertNotFound();
        asToken($owner, 'POST', $uri, ['name' => 'Injected', 'slug' => 'injected'])->assertNotFound();

        expect(ApiSpecification::count())->toBe(0);
    });
});

describe('Updating API specifications', function () {
    it('updates a specification and audits only the changed fields', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($member, $organization))
            ->create(['description' => null]);

        asToken($member, 'PATCH', specificationUrl($specification), [
            'description' => 'Contract for partners',
        ])->assertOk()->assertJsonPath('data.description', 'Contract for partners');

        expect(AuditLog::where('action', 'api_specification_updated')->sole()->meta)->toBe(['fields' => ['description']]);
    });

    it('does not audit an update that changes nothing', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, $organization))
            ->create(['name' => 'Public API']);

        asToken($owner, 'PATCH', specificationUrl($specification), ['name' => 'Public API'])->assertOk();

        expect(AuditLog::where('action', 'api_specification_updated')->exists())->toBeFalse();
    });

    it('keeps slugs unique within the application on update', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationOfProjectCreatedBy($owner, $organization);
        ApiSpecification::factory()->for($application)->create(['slug' => 'taken']);
        $specification = ApiSpecification::factory()->for($application)->create(['slug' => 'mine']);

        asToken($owner, 'PATCH', specificationUrl($specification), ['slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
        asToken($owner, 'PATCH', specificationUrl($specification), ['slug' => 'mine'])->assertOk();

        expect($specification->fresh()->slug)->toBe('mine');
    });

    it('returns 409 when the specification or an ancestor is archived', function (array $projectAttributes, array $applicationAttributes, bool $specificationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, $organization, $projectAttributes, $applicationAttributes))
            ->create(['name' => 'Frozen', 'archived_at' => $specificationArchived ? now() : null]);

        asToken($owner, 'PATCH', specificationUrl($specification), ['name' => 'Changed'])
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($specification->fresh()->name)->toBe('Frozen')
            ->and(AuditLog::where('action', 'api_specification_updated')->exists())->toBeFalse();
    })->with([
        'archived specification' => [[], [], true, 'The API specification is archived.'],
        'archived application' => [[], ['archived_at' => now()], false, 'The application is archived.'],
        'archived project' => [['archived_at' => now()], [], false, 'The project is archived.'],
    ]);
});

describe('Archiving API specifications', function () {
    it('lets the project creator archive and restore a specification someone else created', function () {
        $creator = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        // Created through another member: the project's creator still governs it.
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($creator, $organization))
            ->create();

        asToken($creator, 'POST', specificationUrl($specification, '/archive'))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        asToken($creator, 'POST', specificationUrl($specification, '/restore'))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        expect($specification->fresh()->archived_at)->toBeNull()
            ->and(AuditLog::where('subject_id', $specification->id)->orderBy('created_at')->pluck('action')->all())
            ->toBe(['api_specification_archived', 'api_specification_restored']);
    });

    it('returns 403 when a member archives or restores under a project it did not create', function (string $operation) {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->for($organization)->for($member)->create();
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, $organization))
            ->create(['archived_at' => $operation === 'restore' ? now() : null]);

        asToken($member, 'POST', specificationUrl($specification, "/{$operation}"))->assertForbidden();

        expect($specification->fresh()->isArchived())->toBe($operation === 'restore')
            ->and(AuditLog::where('subject_id', $specification->id)->exists())->toBeFalse();
    })->with(['archive', 'restore']);

    it('lets owners and managers archive any specification of the organization', function (OrganizationRole $role) {
        $creator = User::factory()->create();
        $actor = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        OrganizationMembership::factory()->for($organization)->for($actor)->create(['role' => $role]);
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($creator, $organization))
            ->create();

        asToken($actor, 'POST', specificationUrl($specification, '/archive'))->assertOk();

        expect($specification->fresh()->isArchived())->toBeTrue();
    })->with([
        'owner' => [OrganizationRole::Owner],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('returns 409 for a transition that does not apply', function (string $operation, array $projectAttributes, array $applicationAttributes, bool $specificationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $specification = ApiSpecification::factory()
            ->for(applicationOfProjectCreatedBy($owner, $organization, $projectAttributes, $applicationAttributes))
            ->create(['archived_at' => $specificationArchived ? now() : null]);

        asToken($owner, 'POST', specificationUrl($specification, "/{$operation}"))
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($specification->fresh()->isArchived())->toBe($specificationArchived)
            ->and(AuditLog::where('subject_id', $specification->id)->exists())->toBeFalse();
    })->with([
        'archive an archived specification' => ['archive', [], [], true, 'The API specification is archived.'],
        'restore an active specification' => ['restore', [], [], false, 'The API specification is not archived.'],
        'archive under an archived application' => ['archive', [], ['archived_at' => now()], false, 'The application is archived.'],
        'restore under an archived project' => ['restore', ['archived_at' => now()], [], true, 'The project is archived.'],
    ]);
});
