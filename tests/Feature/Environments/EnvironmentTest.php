<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Projects\Actions\CreateEnvironment;
use App\Domain\Projects\Enums\EnvironmentClassification;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\DTOs\CreateEnvironmentDto;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function environmentsUrl(Application $application, string $suffix = ''): string
{
    return "/api/v1/organizations/{$application->organization_id}/projects/{$application->project_id}"
        ."/applications/{$application->id}/environments{$suffix}";
}

/**
 * Application under a project created by the user in the organization.
 *
 * @param  array<string, mixed>  $projectAttributes
 * @param  array<string, mixed>  $applicationAttributes
 */
function applicationUnderProjectOf(User $user, Organization $organization, array $projectAttributes = [], array $applicationAttributes = []): Application
{
    return Application::factory()
        ->for(projectCreatedBy($user, $organization, $projectAttributes))
        ->create($applicationAttributes);
}

/**
 * @return array<string, string>
 */
function validEnvironmentPayload(array $overrides = []): array
{
    return [
        'name' => 'Staging',
        'slug' => 'staging',
        'classification' => 'staging',
        'authorized_scope' => 'staging.example.test; read-only test accounts',
        ...$overrides,
    ];
}

describe('Creating environments', function () {
    it('creates an environment with the creator as the default responsible and audits it', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationUnderProjectOf($member, $organization);

        $id = asToken($member, 'POST', environmentsUrl($application), validEnvironmentPayload([
            'authorization_reference' => 'TICKET-42',
        ]))->assertCreated()
            ->assertJsonPath('data.application_id', $application->id)
            ->assertJsonPath('data.classification', 'staging')
            ->assertJsonPath('data.authorization_reference', 'TICKET-42')
            ->assertJsonPath('data.responsible_membership_id', $member->organizationMemberships()->sole()->id)
            ->json('data.id');

        $audit = AuditLog::where('action', 'environment_created')->sole();

        expect(Environment::findOrFail($id)->organization_id)->toBe($organization->id);
        expect($audit->organization_id)->toBe($organization->id)
            ->and($audit->user_id)->toBe($member->id)
            ->and($audit->subject_id)->toBe($id);
    });

    it('assigns another active membership of the organization as responsible', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $responsible = OrganizationMembership::factory()->for($organization)->create();

        asToken($owner, 'POST', environmentsUrl($application), validEnvironmentPayload([
            'responsible_membership_id' => $responsible->id,
        ]))->assertCreated()->assertJsonPath('data.responsible_membership_id', $responsible->id);
    });

    it('rejects a responsible that is not an active membership of the organization with 422', function (string $case) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $responsibleId = match ($case) {
            'another organization' => OrganizationMembership::factory()->create()->id,
            'disabled' => OrganizationMembership::factory()->disabled()->for($organization)->create()->id,
            'unknown' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        };

        asToken($owner, 'POST', environmentsUrl($application), validEnvironmentPayload([
            'responsible_membership_id' => $responsibleId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('responsible_membership_id');

        expect(Environment::count())->toBe(0);
    })->with(['another organization', 'disabled', 'unknown']);

    it('rechecks the responsible membership inside the action', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        // Disabled after validation would have passed.
        $responsible = OrganizationMembership::factory()->for($organization)->create();
        $responsible->update(['status' => OrganizationMembershipStatus::Disabled]);

        expect(fn () => CreateEnvironment::execute(
            $organization,
            $application->project,
            $application,
            new CreateEnvironmentDto('Staging', 'staging', EnvironmentClassification::Staging, 'staging.example.test', null, $responsible->id),
            $owner,
            AuditContext::system(),
        ))->toThrow(ValidationException::class);

        expect(Environment::count())->toBe(0);
    });

    it('rejects invalid environment data with 422', function (array $overrides, string $field) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        Environment::factory()->for($application)->create(['slug' => 'taken']);

        asToken($owner, 'POST', environmentsUrl($application), validEnvironmentPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Environment::count())->toBe(1);
    })->with([
        'missing classification' => [['classification' => ''], 'classification'],
        'unknown classification' => [['classification' => 'qa'], 'classification'],
        'missing authorized scope' => [['authorized_scope' => ''], 'authorized_scope'],
        'authorized scope too long' => [['authorized_scope' => str_repeat('a', 5001)], 'authorized_scope'],
        'authorization reference too long' => [['authorization_reference' => str_repeat('a', 256)], 'authorization_reference'],
        'slug taken in the application' => [['slug' => 'taken'], 'slug'],
    ]);

    it('ignores tenant and application fields sent in the payload', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $sibling = Application::factory()->for($application->project)->create();

        $id = asToken($owner, 'POST', environmentsUrl($application), validEnvironmentPayload([
            'organization_id' => Organization::factory()->create()->id,
            'application_id' => $sibling->id,
        ]))->assertCreated()->json('data.id');

        expect(Environment::findOrFail($id)->application_id)->toBe($application->id)
            ->and(Environment::findOrFail($id)->organization_id)->toBe($organization->id);
    });

    it('returns 409 when creating under an archived ancestor', function (bool $projectArchived, bool $applicationArchived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf(
            $owner,
            $organization,
            ['archived_at' => $projectArchived ? now() : null],
            ['archived_at' => $applicationArchived ? now() : null],
        );

        asToken($owner, 'POST', environmentsUrl($application), validEnvironmentPayload())
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect(Environment::count())->toBe(0);
    })->with([
        'archived application' => [false, true, 'The application is archived.'],
        'archived project' => [true, false, 'The project is archived.'],
    ]);
});

describe('Listing and viewing environments', function () {
    it('lists the active environments of the application and filters by classification', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationUnderProjectOf($member, $organization);
        Environment::factory()->for($application)->create(['name' => 'Production', 'classification' => EnvironmentClassification::Production]);
        Environment::factory()->for($application)->create(['name' => 'Staging']);
        Environment::factory()->for($application)->archived()->create(['name' => 'Old staging']);
        Environment::factory()->for(Application::factory()->for($application->project))->create(['name' => 'Sibling']);

        $all = asToken($member, 'GET', environmentsUrl($application))->assertOk()->json('data.*.name');
        $production = asToken($member, 'GET', environmentsUrl($application, '?classification=production'))
            ->assertOk()
            ->json('data.*.name');

        expect($all)->toBe(['Production', 'Staging'])
            ->and($production)->toBe(['Production']);
    });

    it('rejects an unknown classification filter with 422', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationUnderProjectOf($member, $organization);

        asToken($member, 'GET', environmentsUrl($application, '?classification=qa'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classification');
    });

    it('returns 404 for an environment of another application of the same project', function (string $method, string $suffix) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $siblingEnvironment = Environment::factory()
            ->for(Application::factory()->for($application->project))
            ->create(['name' => 'Sibling']);

        asToken($owner, $method, environmentsUrl($application, "/{$siblingEnvironment->id}{$suffix}"), [
            'name' => 'Hijacked',
        ])->assertNotFound();

        expect($siblingEnvironment->fresh()->name)->toBe('Sibling')
            ->and($siblingEnvironment->fresh()->archived_at)->toBeNull();
    })->with([
        'show' => ['GET', ''],
        'update' => ['PATCH', ''],
        'archive' => ['POST', '/archive'],
    ]);

    it('returns 404 for the environments of an application of another organization', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $foreignApplication = Application::factory()->create();
        $ownProject = projectCreatedBy($owner, $organization);

        asToken($owner, 'GET', "/api/v1/organizations/{$organization->id}/projects/{$ownProject->id}/applications/{$foreignApplication->id}/environments")
            ->assertNotFound();
        asToken($owner, 'POST', "/api/v1/organizations/{$organization->id}/projects/{$foreignApplication->project_id}/applications/{$foreignApplication->id}/environments", validEnvironmentPayload())
            ->assertNotFound();

        expect(Environment::count())->toBe(0);
    });

    it('denies an environment outside the given application without relying on the route binding', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $siblingEnvironment = Environment::factory()->for(Application::factory()->for($application->project))->create();
        $gate = Gate::forUser($owner);
        $arguments = [$siblingEnvironment, $application, $application->project, $organization];

        expect($gate->allows('view', $arguments))->toBeFalse()
            ->and($gate->allows('update', $arguments))->toBeFalse()
            ->and($gate->allows('archive', $arguments))->toBeFalse()
            ->and($gate->allows('view', [$siblingEnvironment, $siblingEnvironment->application, $application->project, $organization]))->toBeTrue();
    });

    it('returns 404 to users without an active membership', function (string $actor) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $environment = Environment::factory()->for($application)->create();
        $user = match ($actor) {
            'outsider' => User::factory()->create(),
            'global admin' => (function () {
                $this->seed();

                return seededUser('admin');
            })->call($this),
        };

        asToken($user, 'GET', environmentsUrl($application))->assertNotFound();
        asToken($user, 'GET', environmentsUrl($application, "/{$environment->id}"))->assertNotFound();
    })->with(['outsider', 'global admin']);
});

describe('Updating environments', function () {
    it('changes the responsible and the scope while auditing only field names', function () {
        $member = User::factory()->create();
        $organization = organizationFor($member, OrganizationRole::Member);
        $application = applicationUnderProjectOf($member, $organization);
        $environment = Environment::factory()->for($application)->create();
        $newResponsible = OrganizationMembership::factory()->for($organization)->create();

        asToken($member, 'PATCH', environmentsUrl($application, "/{$environment->id}"), [
            'responsible_membership_id' => $newResponsible->id,
            'authorized_scope' => 'api.staging.example.test only',
        ])->assertOk()
            ->assertJsonPath('data.responsible_membership_id', $newResponsible->id)
            ->assertJsonPath('data.authorized_scope', 'api.staging.example.test only');

        $audit = AuditLog::where('action', 'environment_updated')->sole();

        expect($audit->meta['fields'])->toEqualCanonicalizing(['authorized_scope', 'responsible_membership_id'])
            ->and(json_encode($audit->getAttributes()))->not->toContain('api.staging.example.test');
    });

    it('rejects a disabled responsible on update with 422', function () {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf($owner, $organization);
        $environment = Environment::factory()->for($application)->create();
        $disabled = OrganizationMembership::factory()->disabled()->for($organization)->create();

        asToken($owner, 'PATCH', environmentsUrl($application, "/{$environment->id}"), [
            'responsible_membership_id' => $disabled->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('responsible_membership_id');

        expect($environment->fresh()->responsible_membership_id)->not->toBe($disabled->id);
    });

    it('returns 409 when the environment or an ancestor is archived', function (array $archived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf(
            $owner,
            $organization,
            ['archived_at' => in_array('project', $archived, true) ? now() : null],
            ['archived_at' => in_array('application', $archived, true) ? now() : null],
        );
        $environment = Environment::factory()->for($application)->create([
            'name' => 'Frozen',
            'archived_at' => in_array('environment', $archived, true) ? now() : null,
        ]);

        asToken($owner, 'PATCH', environmentsUrl($application, "/{$environment->id}"), ['name' => 'Changed'])
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($environment->fresh()->name)->toBe('Frozen')
            ->and(AuditLog::where('action', 'environment_updated')->exists())->toBeFalse();
    })->with([
        'archived environment' => [['environment'], 'The environment is archived.'],
        'archived application' => [['application'], 'The application is archived.'],
        'archived project' => [['project'], 'The project is archived.'],
    ]);
});

describe('Archiving environments', function () {
    it('lets the project creator archive and restore an environment', function () {
        $creator = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        $application = applicationUnderProjectOf($creator, $organization);
        $environment = Environment::factory()->for($application)->create();

        asToken($creator, 'POST', environmentsUrl($application, "/{$environment->id}/archive"))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
        asToken($creator, 'POST', environmentsUrl($application, "/{$environment->id}/restore"))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        expect(AuditLog::where('subject_id', $environment->id)->orderBy('created_at')->pluck('action')->all())
            ->toBe(['environment_archived', 'environment_restored']);
    });

    it('returns 403 when a member archives or restores under a project it did not create', function (string $operation) {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = organizationFor($owner);
        // Being the environment's responsible does not grant archiving.
        $membership = OrganizationMembership::factory()->for($organization)->for($member)->create();
        $application = applicationUnderProjectOf($owner, $organization);
        $environment = Environment::factory()->for($application)->responsible($membership)->create([
            'archived_at' => $operation === 'restore' ? now() : null,
        ]);

        asToken($member, 'POST', environmentsUrl($application, "/{$environment->id}/{$operation}"))
            ->assertForbidden();

        expect($environment->fresh()->isArchived())->toBe($operation === 'restore')
            ->and(AuditLog::where('subject_id', $environment->id)->exists())->toBeFalse();
    })->with(['archive', 'restore']);

    it('lets owners and managers archive any environment of the organization', function (OrganizationRole $role) {
        $creator = User::factory()->create();
        $actor = User::factory()->create();
        $organization = organizationFor($creator, OrganizationRole::Member);
        OrganizationMembership::factory()->for($organization)->for($actor)->create(['role' => $role]);
        $application = applicationUnderProjectOf($creator, $organization);
        $environment = Environment::factory()->for($application)->create();

        asToken($actor, 'POST', environmentsUrl($application, "/{$environment->id}/archive"))->assertOk();

        expect($environment->fresh()->isArchived())->toBeTrue();
    })->with([
        'owner' => [OrganizationRole::Owner],
        'manager' => [OrganizationRole::Manager],
    ]);

    it('returns 409 for a transition that does not apply', function (string $operation, array $archived, string $message) {
        $owner = User::factory()->create();
        $organization = organizationFor($owner);
        $application = applicationUnderProjectOf(
            $owner,
            $organization,
            ['archived_at' => in_array('project', $archived, true) ? now() : null],
            ['archived_at' => in_array('application', $archived, true) ? now() : null],
        );
        $environment = Environment::factory()->for($application)->create([
            'archived_at' => in_array('environment', $archived, true) ? now() : null,
        ]);

        asToken($owner, 'POST', environmentsUrl($application, "/{$environment->id}/{$operation}"))
            ->assertConflict()
            ->assertJsonPath('message', $message);

        expect($environment->fresh()->isArchived())->toBe(in_array('environment', $archived, true))
            ->and(AuditLog::where('subject_id', $environment->id)->exists())->toBeFalse();
    })->with([
        'archive an archived environment' => ['archive', ['environment'], 'The environment is archived.'],
        'restore an active environment' => ['restore', [], 'The environment is not archived.'],
        'archive under an archived application' => ['archive', ['application'], 'The application is archived.'],
        'restore under an archived application' => ['restore', ['application', 'environment'], 'The application is archived.'],
        'archive under an archived project' => ['archive', ['project'], 'The project is archived.'],
    ]);
});
