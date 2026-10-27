<?php

use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function applicationsUrl(Project $project, string $suffix = ''): string
{
    return "/api/v1/organizations/{$project->organization_id}/projects/{$project->id}/applications{$suffix}";
}

describe('Creating applications', function () {
    it('creates an application in the project and audits it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->for($organization)->for($member)->create();
        $project = projectCreatedBy($owner, $organization);

        $id = asToken($member, 'POST', applicationsUrl($project), [
            'name' => 'Checkout API',
            'slug' => 'checkout-api',
        ])->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $audit = AuditLog::where('action', 'application_created')->sole();

        expect(Application::findOrFail($id)->project_id)->toBe($project->id);
        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->user_id)->toBe($member->id)
            ->and($audit->subject_id)->toBe($id);
    });

    it('ignores tenant and project fields sent in the payload', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $sibling = projectCreatedBy($owner, $organization);
        $foreignProject = Project::factory()->create();

        $id = asToken($owner, 'POST', applicationsUrl($project), [
            'name' => 'Checkout API',
            'slug' => 'checkout-api',
            'organization_id' => $foreignProject->organization_id,
            'project_id' => $sibling->id,
        ])->assertCreated()->json('data.id');

        expect(Application::findOrFail($id)->project_id)->toBe($project->id)
            ->and(Application::findOrFail($id)->organization_id)->toBe($organization->id);
    });

    it('rejects invalid application data with 422', function (array $payload, string $field) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        Application::factory()->for($project)->create(['slug' => 'taken']);

        asToken($owner, 'POST', applicationsUrl($project), [
            'name' => 'Valid name',
            'slug' => 'valid-slug',
            ...$payload,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Application::count())->toBe(1);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'malformed slug' => [['slug' => 'Not Valid'], 'slug'],
        'slug taken in the project' => [['slug' => 'taken'], 'slug'],
    ]);

    it('accepts a slug already used by another project of the organization', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        Application::factory()->for(projectCreatedBy($owner, $organization))->create(['slug' => 'checkout-api']);

        asToken($owner, 'POST', applicationsUrl($project), [
            'name' => 'Checkout API',
            'slug' => 'checkout-api',
        ])->assertCreated();

        expect(Application::where('slug', 'checkout-api')->count())->toBe(2);
    });

    it('returns 409 when creating under an archived project', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization, ['archived_at' => now()]);

        asToken($owner, 'POST', applicationsUrl($project), [
            'name' => 'Checkout API',
            'slug' => 'checkout-api',
        ])->assertConflict()->assertJsonPath('message', 'The project is archived.');

        expect(Application::count())->toBe(0)
            ->and(AuditLog::where('action', 'application_created')->exists())->toBeFalse();
    });
});

describe('Listing and viewing applications', function () {
    it('lists only the active applications of the project by default', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);
        $active = Application::factory()->for($project)->create(['name' => 'Active']);
        Application::factory()->for($project)->archived()->create(['name' => 'Archived']);
        Application::factory()->for(projectCreatedBy($member, $organization))->create(['name' => 'Sibling project']);

        asToken($member, 'GET', applicationsUrl($project))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.total', 1);
    });

    it('filters applications by status and name', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);
        Application::factory()->for($project)->create(['name' => 'Checkout API']);
        Application::factory()->for($project)->archived()->create(['name' => 'Legacy checkout']);
        Application::factory()->for($project)->create(['name' => 'Payments']);

        $names = asToken($member, 'GET', applicationsUrl($project, '?status=all&search=CHECKOUT'))
            ->assertOk()
            ->json('data.*.name');

        expect($names)->toBe(['Checkout API', 'Legacy checkout']);
    });

    it('rejects an unknown status filter with 422', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);

        asToken($member, 'GET', applicationsUrl($project, '?status=deleted'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('keeps the applications of an archived project readable', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization, ['archived_at' => now()]);
        $application = Application::factory()->for($project)->create();

        asToken($member, 'GET', applicationsUrl($project))->assertOk()->assertJsonCount(1, 'data');
        asToken($member, 'GET', applicationsUrl($project, "/{$application->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $application->id);
    });

    it('returns 404 for an application of another project of the same organization', function (string $method, string $suffix) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $siblingApplication = Application::factory()
            ->for(projectCreatedBy($owner, $organization))
            ->create(['name' => 'Sibling']);

        asToken($owner, $method, applicationsUrl($project, "/{$siblingApplication->id}{$suffix}"), [
            'name' => 'Hijacked',
        ])->assertNotFound();

        expect($siblingApplication->fresh()->name)->toBe('Sibling')
            ->and($siblingApplication->fresh()->archived_at)->toBeNull();
    })->with([
        'show' => ['GET', ''],
        'update' => ['PATCH', ''],
        'archive' => ['POST', '/archive'],
    ]);

    it('denies an application outside the given project without relying on the route binding', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $siblingApplication = Application::factory()->for(projectCreatedBy($owner, $organization))->create();
        $gate = Gate::forUser($owner);

        expect($gate->allows('view', [$siblingApplication, $project, $organization]))->toBeFalse()
            ->and($gate->allows('update', [$siblingApplication, $project, $organization]))->toBeFalse()
            ->and($gate->allows('archive', [$siblingApplication, $project, $organization]))->toBeFalse()
            ->and($gate->allows('view', [$siblingApplication, $siblingApplication->project, $organization]))->toBeTrue();
    });

    it('returns 404 for the applications of a project of another organization', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $foreignProject = Project::factory()->create();
        Application::factory()->for($foreignProject)->create();

        asToken($owner, 'GET', "/api/v1/organizations/{$organization->id}/projects/{$foreignProject->id}/applications")
            ->assertNotFound();
        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$foreignProject->id}/applications", [
            'name' => 'Injected',
            'slug' => 'injected',
        ])->assertNotFound();

        expect(Application::where('slug', 'injected')->exists())->toBeFalse();
    });

    it('returns 404 to users without an active membership', function (string $actor) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization);
        $application = Application::factory()->for($project)->create();
        $user = match ($actor) {
            'outsider' => User::factory()->create(),
            'disabled member' => tap(User::factory()->create(), fn (User $user) => OrganizationMembership::factory()
                ->disabled()->for($organization)->for($user)->create()),
            'global admin' => (function () {
                $this->seed();

                return seededUser('admin');
            })->call($this),
        };

        asToken($user, 'GET', applicationsUrl($project))->assertNotFound();
        asToken($user, 'GET', applicationsUrl($project, "/{$application->id}"))->assertNotFound();
    })->with(['outsider', 'disabled member', 'global admin']);
});

describe('Updating applications', function () {
    it('updates an application and audits only the changed fields', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $project = projectCreatedBy($member, $organization);
        $application = Application::factory()->for($project)->create(['description' => null]);

        asToken($member, 'PATCH', applicationsUrl($project, "/{$application->id}"), [
            'description' => 'Public checkout API',
        ])->assertOk()->assertJsonPath('data.description', 'Public checkout API');

        expect(AuditLog::where('action', 'application_updated')->sole()->meta)->toBe(['fields' => ['description']]);
    });

    it('returns 409 when the application or its project is archived', function (bool $projectArchived, bool $applicationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization, ['archived_at' => $projectArchived ? now() : null]);
        $application = Application::factory()->for($project)->create([
            'name' => 'Frozen',
            'archived_at' => $applicationArchived ? now() : null,
        ]);

        asToken($owner, 'PATCH', applicationsUrl($project, "/{$application->id}"), ['name' => 'Changed'])
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($application->fresh()->name)->toBe('Frozen')
            ->and(AuditLog::where('action', 'application_updated')->exists())->toBeFalse();
    })->with([
        'archived application' => [false, true, 'The application is archived.'],
        'archived project' => [true, false, 'The project is archived.'],
    ]);
});

describe('Archiving applications', function () {
    it('lets the project creator archive and restore an application someone else created', function () {
        $creator = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        $project = projectCreatedBy($creator, $organization);
        // Created through another member: the project's creator still governs it.
        $application = Application::factory()->for($project)->create();

        asToken($creator, 'POST', applicationsUrl($project, "/{$application->id}/archive"))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        asToken($creator, 'POST', applicationsUrl($project, "/{$application->id}/restore"))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        expect($application->fresh()->archived_at)->toBeNull()
            ->and(AuditLog::where('subject_id', $application->id)->orderBy('created_at')->pluck('action')->all())
            ->toBe(['application_archived', 'application_restored']);
    });

    it('returns 403 when a member archives or restores under a project it did not create', function (string $operation) {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        OrganizationMembership::factory()->for($organization)->for($member)->create();
        $project = projectCreatedBy($owner, $organization);
        $application = Application::factory()->for($project)->create([
            'archived_at' => $operation === 'restore' ? now() : null,
        ]);

        asToken($member, 'POST', applicationsUrl($project, "/{$application->id}/{$operation}"))
            ->assertForbidden();

        expect($application->fresh()->isArchived())->toBe($operation === 'restore')
            ->and(AuditLog::where('subject_id', $application->id)->exists())->toBeFalse();
    })->with(['archive', 'restore']);

    it('lets owners and managers archive any application of the organization', function (OrganizationRole $role) {
        $creator = User::factory()->create();
        $actor = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        OrganizationMembership::factory()->for($organization)->for($actor)->create(['role' => $role]);
        $project = projectCreatedBy($creator, $organization);
        $application = Application::factory()->for($project)->create();

        asToken($actor, 'POST', applicationsUrl($project, "/{$application->id}/archive"))->assertOk();

        expect($application->fresh()->isArchived())->toBeTrue();
    })->with([
        'owner' => [OrganizationRole::Owner],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('returns 409 for a transition that does not apply', function (string $operation, bool $projectArchived, bool $applicationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $project = projectCreatedBy($owner, $organization, ['archived_at' => $projectArchived ? now() : null]);
        $application = Application::factory()->for($project)->create([
            'archived_at' => $applicationArchived ? now() : null,
        ]);

        asToken($owner, 'POST', applicationsUrl($project, "/{$application->id}/{$operation}"))
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($application->fresh()->isArchived())->toBe($applicationArchived)
            ->and(AuditLog::where('subject_id', $application->id)->exists())->toBeFalse();
    })->with([
        'archive an archived application' => ['archive', false, true, 'The application is archived.'],
        'restore an active application' => ['restore', false, false, 'The application is not archived.'],
        'archive under an archived project' => ['archive', true, false, 'The project is archived.'],
        'restore under an archived project' => ['restore', true, true, 'The project is archived.'],
    ]);
});
