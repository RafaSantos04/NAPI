<?php

use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// P2-T05: adversarial checks across the whole project tree. Per-resource
// behavior lives in tests/Feature/{Projects,Applications,Environments}; this
// file covers what falls between them (ADR-0012, threat model "Tenancy (P2)").

/**
 * A full tree in a fresh organization, created by $creator with $role.
 *
 * @return array{organization: Organization, project: Project, application: Application, environment: Environment}
 */
function projectTreeFor(User $creator, OrganizationRole $role = OrganizationRole::Owner): array
{
    $organization = organizationFor($creator, $role);
    $project = projectCreatedBy($creator, $organization);
    $application = Application::factory()->for($project)->create();
    $environment = Environment::factory()->for($application)->create();

    return compact('organization', 'project', 'application', 'environment');
}

/**
 * @param  array{organization: Organization, project: Project, application: Application, environment: Environment}  $tree
 */
function treeUri(array $tree, string $template): string
{
    return '/'.strtr($template, [
        '{organization}' => $tree['organization']->id,
        '{project}' => $tree['project']->id,
        '{application}' => $tree['application']->id,
        '{environment}' => $tree['environment']->id,
    ]);
}

/**
 * Every row of the tree tables, to prove a request changed nothing.
 *
 * @return array<string, mixed>
 */
function projectTreeSnapshot(): array
{
    return collect(['projects', 'applications', 'environments'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])
        ->all();
}

/**
 * @return array<int, RoutingRoute>
 */
function projectTreeRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => Str::startsWith((string) $route->getName(), 'organizations.projects.'))
        ->values()
        ->all();
}

/**
 * The write operations of the tree: [method, uri template, payload, audit action, audited subject].
 *
 * @return array{0: string, 1: string, 2: array<string, string>, 3: string, 4: ?string}
 */
function projectTreeOperation(string $operation): array
{
    $project = 'api/v1/organizations/{organization}/projects';
    $application = "{$project}/{project}/applications";
    $environment = "{$application}/{application}/environments";

    return match ($operation) {
        'project.create' => ['POST', $project, ['name' => 'New project', 'slug' => 'new-project'], 'project_created', null],
        'project.update' => ['PATCH', "{$project}/{project}", ['name' => 'Renamed'], 'project_updated', 'project'],
        'project.archive' => ['POST', "{$project}/{project}/archive", [], 'project_archived', 'project'],
        'project.restore' => ['POST', "{$project}/{project}/restore", [], 'project_restored', 'project'],
        'application.create' => ['POST', $application, ['name' => 'New application', 'slug' => 'new-application'], 'application_created', null],
        'application.update' => ['PATCH', "{$application}/{application}", ['name' => 'Renamed'], 'application_updated', 'application'],
        'application.archive' => ['POST', "{$application}/{application}/archive", [], 'application_archived', 'application'],
        'application.restore' => ['POST', "{$application}/{application}/restore", [], 'application_restored', 'application'],
        'environment.create' => ['POST', $environment, [
            'name' => 'New environment',
            'slug' => 'new-environment',
            'classification' => 'development',
            'authorized_scope' => 'dev.example.test',
        ], 'environment_created', null],
        'environment.update' => ['PATCH', "{$environment}/{environment}", ['name' => 'Renamed'], 'environment_updated', 'environment'],
        'environment.archive' => ['POST', "{$environment}/{environment}/archive", [], 'environment_archived', 'environment'],
        'environment.restore' => ['POST', "{$environment}/{environment}/restore", [], 'environment_restored', 'environment'],
    };
}

/**
 * Restoring needs an archived target; every other operation an active tree.
 *
 * @param  array{organization: Organization, project: Project, application: Application, environment: Environment}  $tree
 */
function prepareProjectTreeOperation(string $operation, array $tree): void
{
    if (str_ends_with($operation, '.restore')) {
        // archived_at is deliberately not fillable; set it as the Actions do.
        $tree[Str::before($operation, '.')]->forceFill(['archived_at' => now()])->save();
    }
}

dataset('project tree write operations', [
    'project.create', 'project.update', 'project.archive', 'project.restore',
    'application.create', 'application.update', 'application.archive', 'application.restore',
    'environment.create', 'environment.update', 'environment.archive', 'environment.restore',
]);

describe('Project tree audit trail', function () {
    it('writes exactly one tenant audit entry for each write operation', function (string $operation) {
        $owner = User::factory()->create();
        $tree = projectTreeFor($owner);
        prepareProjectTreeOperation($operation, $tree);
        [$method, $uri, $payload, $action, $subject] = projectTreeOperation($operation);
        $before = AuditLog::count();

        $response = asToken($owner, $method, treeUri($tree, $uri), $payload)->assertSuccessful();

        $entry = AuditLog::where('action', $action)->sole();
        $subjectModel = $subject === null ? null : $tree[$subject];

        expect(AuditLog::count())->toBe($before + 1);
        expect($entry->organization_id)->toBe($tree['organization']->id)
            ->and($entry->user_id)->toBe($owner->id)
            ->and($entry->subject_id)->toBe($subjectModel?->id ?? $response->json('data.id'))
            ->and($entry->subject_type)->toBe($subjectModel !== null
                ? $subjectModel::class
                : 'App\\Models\\'.Str::studly(Str::before($operation, '.')));
    })->with('project tree write operations');

    it('undoes each write operation when its audit entry cannot be stored', function (string $operation) {
        $owner = User::factory()->create();
        $tree = projectTreeFor($owner);
        prepareProjectTreeOperation($operation, $tree);
        [$method, $uri, $payload] = projectTreeOperation($operation);
        $snapshot = projectTreeSnapshot();
        AuditLog::creating(fn () => throw new RuntimeException('Audit storage unavailable.'));

        asToken($owner, $method, treeUri($tree, $uri), $payload)->assertServerError();

        expect(projectTreeSnapshot())->toBe($snapshot);
    })->with('project tree write operations');
});

describe('Project tree isolation', function () {
    it('returns 404 on every tree route to users without an active membership', function (string $actor) {
        $tree = projectTreeFor(User::factory()->create());
        $user = match ($actor) {
            'outsider' => User::factory()->create(),
            'member of another organization' => tap(User::factory()->create(), fn (User $user) => projectTreeFor($user)),
            'disabled member' => tap(User::factory()->create(), fn (User $user) => OrganizationMembership::factory()
                ->disabled()->for($tree['organization'])->for($user)->create(['role' => OrganizationRole::Owner])),
            'global admin' => seededUser('admin'),
        };
        $routes = projectTreeRoutes();
        $snapshot = projectTreeSnapshot();
        $auditCount = AuditLog::count();

        foreach ($routes as $route) {
            $method = collect($route->methods())->reject(fn (string $method) => $method === 'HEAD')->first();

            asToken($user, $method, treeUri($tree, $route->uri()), ['name' => 'Injected'])
                ->assertNotFound();
        }

        // Guards against a vacuous loop if the route names ever change.
        expect(count($routes))->toBeGreaterThanOrEqual(18);
        expect(projectTreeSnapshot())->toBe($snapshot)
            ->and(AuditLog::count())->toBe($auditCount);
    })->with(['outsider', 'member of another organization', 'disabled member', 'global admin']);

    it('returns 404 when a member of two organizations mixes their resources in one URL', function (string $template) {
        $user = User::factory()->create();
        $ownTree = projectTreeFor($user);
        $otherTree = projectTreeFor($user);
        // The other organization's ids under this organization's prefix.
        $mixed = [...$ownTree, ...array_filter(
            $otherTree,
            fn (string $key) => $key !== 'organization' && str_contains($template, "{{$key}}"),
            ARRAY_FILTER_USE_KEY,
        )];
        $snapshot = projectTreeSnapshot();

        asToken($user, 'POST', treeUri($mixed, $template), ['name' => 'Injected', 'slug' => 'injected'])
            ->assertNotFound();

        expect(projectTreeSnapshot())->toBe($snapshot);
    })->with([
        'foreign project' => ['api/v1/organizations/{organization}/projects/{project}/archive'],
        'foreign project, own application' => ['api/v1/organizations/{organization}/projects/{project}/applications'],
        'foreign application' => ['api/v1/organizations/{organization}/projects/{project}/applications/{application}/archive'],
        'foreign environment' => ['api/v1/organizations/{organization}/projects/{project}/applications/{application}/environments/{environment}/archive'],
    ]);

    it('applies the role of the organization in the URL, not the strongest one', function (string $level) {
        $user = User::factory()->create();
        projectTreeFor($user, OrganizationRole::Owner);
        // A member of this organization, under a project someone else created.
        $creator = User::factory()->create();
        $tree = projectTreeFor($creator);
        OrganizationMembership::factory()->for($tree['organization'])->for($user)->create();
        $template = match ($level) {
            'project' => 'api/v1/organizations/{organization}/projects/{project}/archive',
            'application' => 'api/v1/organizations/{organization}/projects/{project}/applications/{application}/archive',
            'environment' => 'api/v1/organizations/{organization}/projects/{project}/applications/{application}/environments/{environment}/archive',
        };

        asToken($user, 'POST', treeUri($tree, $template))->assertForbidden();

        expect($tree[$level]->fresh()->isArchived())->toBeFalse();
    })->with(['project', 'application', 'environment']);

    it('returns 404 for malformed or unknown ids at every level', function (string $level, string $id) {
        $owner = User::factory()->create();
        $tree = projectTreeFor($owner);
        $template = 'api/v1/organizations/{organization}/projects/{project}/applications/{application}/environments/{environment}';
        $uri = str_replace(rawurlencode($tree[$level]->id), rawurlencode($id), treeUri($tree, $template));

        asToken($owner, 'GET', $uri)->assertNotFound();
    })->with(['project', 'application', 'environment'])->with([
        'sql fragment' => ["abc' OR '1'='1"],
        'oversized' => [str_repeat('A', 40)],
        'unknown ulid' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
    ]);
});

describe('Project tree mass assignment', function () {
    it('ignores tenant, parent, creator and lifecycle fields sent to PATCH', function (string $level, string $field) {
        $owner = User::factory()->create();
        $tree = projectTreeFor($owner);
        $otherTree = projectTreeFor($owner);
        $sibling = match ($level) {
            'project' => OrganizationMembership::factory()->for($tree['organization'])->create(),
            'application' => projectCreatedBy($owner, $tree['organization']),
            'environment' => Application::factory()->for($tree['project'])->create(),
        };
        $value = match ($field) {
            'organization_id' => $otherTree['organization']->id,
            'archived_at' => now()->toIso8601String(),
            default => $sibling->id,
        };
        $template = match ($level) {
            'project' => 'api/v1/organizations/{organization}/projects/{project}',
            'application' => 'api/v1/organizations/{organization}/projects/{project}/applications/{application}',
            'environment' => 'api/v1/organizations/{organization}/projects/{project}/applications/{application}/environments/{environment}',
        };
        $original = $tree[$level]->getRawOriginal($field);

        asToken($owner, 'PATCH', treeUri($tree, $template), ['name' => 'Renamed', $field => $value])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        expect($tree[$level]->fresh()->getRawOriginal($field))->toBe($original);
    })->with([
        'project tenant' => ['project', 'organization_id'],
        'project creator' => ['project', 'created_by_membership_id'],
        'project lifecycle' => ['project', 'archived_at'],
        'application tenant' => ['application', 'organization_id'],
        'application parent' => ['application', 'project_id'],
        'application lifecycle' => ['application', 'archived_at'],
        'environment tenant' => ['environment', 'organization_id'],
        'environment parent' => ['environment', 'application_id'],
        'environment lifecycle' => ['environment', 'archived_at'],
    ]);
});
