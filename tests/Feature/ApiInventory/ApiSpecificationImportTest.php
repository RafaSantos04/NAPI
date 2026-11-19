<?php

use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\ApiEndpoint;
use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;

// P3-T04 (ADR-0014, decisions 10 to 20): the import turns a raw OpenAPI
// body into an immutable version and its endpoint inventory.

// Stands for the client's confidential content: it must never reach the
// audit trail nor a response.
const IMPORT_CONTENT_MARKER = 'confidential-contract-text';

/**
 * A specification of an application in a fresh organization owned by $owner.
 */
function importableSpecification(User $owner, OrganizationRole $role = OrganizationRole::Owner): ApiSpecification
{
    $organization = organizationFor($owner, $role);

    return ApiSpecification::factory()
        ->for(Application::factory()->for(projectCreatedBy($owner, $organization)))
        ->create();
}

/**
 * An OpenAPI 3.1 document with the given operations ([method, path] pairs).
 *
 * @param  list<array{0: string, 1: string}>  $operations
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function openApiDocumentWith(array $operations = [['get', '/orders'], ['post', '/orders']], array $overrides = []): array
{
    $paths = [];
    foreach ($operations as [$method, $path]) {
        $paths[$path][$method] = ['summary' => "{$method} {$path}", 'responses' => ['200' => ['description' => 'OK']]];
    }

    return [
        'openapi' => '3.1.0',
        'info' => ['title' => 'Orders', 'version' => '1.0.0', 'description' => IMPORT_CONTENT_MARKER],
        'paths' => $paths,
        ...$overrides,
    ];
}

/**
 * Sends $body as the raw request body, as a client or a CI pipeline would.
 *
 * @param  array<string, mixed>  $server
 */
function importInto(ApiSpecification $specification, ?User $user, string $body, string $contentType = 'application/json', array $server = []): TestResponse
{
    app('auth')->forgetGuards();

    return test()->call(
        'POST',
        "/api/v1/organizations/{$specification->organization_id}/api-specifications/{$specification->id}/versions",
        server: [
            ...($user === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.tokenFor($user)]),
            'CONTENT_TYPE' => $contentType,
            'HTTP_ACCEPT' => 'application/json',
            ...$server,
        ],
        content: $body,
    );
}

function versionsUrl(ApiSpecification $specification, string $suffix = ''): string
{
    return "/api/v1/organizations/{$specification->organization_id}/api-specifications/{$specification->id}/versions{$suffix}";
}

describe('Importing OpenAPI documents', function () {
    it('imports a JSON document as version 1 with its endpoint inventory and audits it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $specification = importableSpecification($owner);
        OrganizationMembership::factory()->for($specification->organization)->for($member)->create(['role' => OrganizationRole::Member]);
        $body = json_encode(openApiDocumentWith(), JSON_THROW_ON_ERROR);

        $response = importInto($specification, $member, $body)
            ->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.format', 'json')
            ->assertJsonPath('data.openapi_version', '3.1.0')
            ->assertJsonPath('data.info_title', 'Orders')
            ->assertJsonPath('data.content_sha256', hash('sha256', $body))
            ->assertJsonPath('data.size_bytes', strlen($body))
            ->assertJsonPath('data.endpoints_count', 2)
            ->assertJsonMissingPath('data.raw_document');
        $version = ApiSpecificationVersion::findOrFail($response->json('data.id'));
        $audit = AuditLog::where('action', 'api_specification_version_imported')->sole();

        expect($version->raw_document)->toBe($body)
            ->and($version->imported_by_membership_id)->toBe($member->organizationMemberships()->sole()->id)
            ->and($version->endpoints()->pluck('method')->sort()->values()->all())->toBe(['GET', 'POST']);
        expect($audit->organization_id)->toBe($specification->organization_id)
            ->and($audit->user_id)->toBe($member->id)
            ->and($audit->subject_id)->toBe($version->id)
            ->and($audit->meta)->toEqual([
                'api_specification_id' => $specification->id,
                'number' => 1,
                'format' => 'json',
                'openapi_version' => '3.1.0',
                'size_bytes' => strlen($body),
                'content_sha256' => hash('sha256', $body),
                'endpoints' => 2,
                'endpoints_added' => 2,
                'endpoints_removed' => 0,
            ]);
        expect($response->getContent())->not->toContain(IMPORT_CONTENT_MARKER);
    });

    it('imports YAML under each accepted media type', function (string $contentType) {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);

        importInto($specification, $owner, Yaml::dump(openApiDocumentWith(), 10), $contentType)
            ->assertCreated()
            ->assertJsonPath('data.format', 'yaml')
            ->assertJsonPath('data.endpoints_count', 2);
    })->with(['application/yaml', 'application/x-yaml', 'text/yaml', 'Application/YAML; charset=utf-8']);

    it('numbers new content as the next version and audits the inventory diff', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        importInto($specification, $owner, json_encode(openApiDocumentWith([['get', '/orders/{id}'], ['delete', '/orders/{id}']])))
            ->assertCreated();

        // Renaming a path parameter keeps the endpoint; DELETE is removed and
        // PATCH added.
        importInto($specification, $owner, json_encode(openApiDocumentWith([['get', '/orders/{orderId}'], ['patch', '/orders/{orderId}']])))
            ->assertCreated()
            ->assertJsonPath('data.number', 2);

        expect(AuditLog::where('action', 'api_specification_version_imported')->latest('id')->first()->meta)
            ->toMatchArray(['number' => 2, 'endpoints' => 2, 'endpoints_added' => 1, 'endpoints_removed' => 1])
            ->and(ApiEndpoint::count())->toBe(4);
    });

    it('returns the existing version for the same content, without a new version or audit entry', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $body = json_encode(openApiDocumentWith(), JSON_THROW_ON_ERROR);
        $id = importInto($specification, $owner, $body)->assertCreated()->json('data.id');
        $auditCount = AuditLog::count();

        importInto($specification, $owner, $body)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.endpoints_count', 2);

        expect(ApiSpecificationVersion::count())->toBe(1)
            ->and(ApiEndpoint::count())->toBe(2)
            ->and(AuditLog::count())->toBe($auditCount);
    });

    it('accepts a title of 255 characters, counted in characters, not bytes', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $title = str_repeat('é', 255);

        importInto($specification, $owner, json_encode(openApiDocumentWith(overrides: ['info' => ['title' => $title, 'version' => str_repeat('9', 100)]])))
            ->assertCreated()
            ->assertJsonPath('data.info_title', $title);
    });

    it('keeps versions per specification and per format', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $sibling = ApiSpecification::factory()->for($specification->application)->create();
        $document = openApiDocumentWith();

        importInto($specification, $owner, json_encode($document))->assertCreated()->assertJsonPath('data.number', 1);
        importInto($sibling, $owner, json_encode($document))->assertCreated()->assertJsonPath('data.number', 1);
        // The same contract in YAML is other content: a new version.
        importInto($specification, $owner, Yaml::dump($document, 10), 'application/yaml')
            ->assertCreated()
            ->assertJsonPath('data.number', 2)
            ->assertJsonPath('data.format', 'yaml');

        expect(ApiSpecificationVersion::count())->toBe(3);
    });

    it('accepts a document above the 64 KB default body limit', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $body = json_encode(openApiDocumentWith(overrides: ['x-padding' => str_repeat('a', 200 * 1024)]), JSON_THROW_ON_ERROR);

        importInto($specification, $owner, $body, server: ['CONTENT_LENGTH' => strlen($body)])->assertCreated();
    });

    it('rolls the version and its inventory back when the audit entry cannot be stored', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        AuditLog::creating(fn () => throw new RuntimeException('Audit storage unavailable.'));

        importInto($specification, $owner, json_encode(openApiDocumentWith()))->assertServerError();

        expect(ApiSpecificationVersion::count())->toBe(0)
            ->and(ApiEndpoint::count())->toBe(0);
    });
});

describe('Refusing OpenAPI imports', function () {
    it('refuses an invalid document with 422, audits the reason without its content and stores nothing', function (string $body, string $contentType, string $reason, string $pointer) {
        // A remote reference is never fetched.
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);

        $response = importInto($specification, $owner, $body, $contentType)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The OpenAPI document is invalid.')
            ->assertJsonPath('errors.0.pointer', $pointer);
        $audit = AuditLog::where('action', 'api_specification_import_rejected')->sole();

        expect($audit->subject_id)->toBe($specification->id)
            ->and($audit->organization_id)->toBe($specification->organization_id)
            ->and($audit->meta)->toEqual([
                'reason' => $reason,
                'pointer' => $pointer,
                'format' => str_contains($contentType, 'yaml') ? 'yaml' : 'json',
                'size_bytes' => strlen($body),
                'content_sha256' => hash('sha256', $body),
            ])
            ->and(json_encode($audit->meta))->not->toContain(IMPORT_CONTENT_MARKER)
            ->and($response->getContent())->not->toContain(IMPORT_CONTENT_MARKER)
            ->and(ApiSpecificationVersion::count())->toBe(0)
            ->and(ApiEndpoint::count())->toBe(0);
    })->with([
        'malformed JSON' => ['{"openapi": "'.IMPORT_CONTENT_MARKER, 'application/json', 'malformed', ''],
        'not UTF-8' => ["{\"openapi\": \"\xFF\"}", 'application/json', 'malformed', ''],
        'Swagger 2.0' => [json_encode(['swagger' => '2.0', 'info' => ['title' => IMPORT_CONTENT_MARKER]]), 'application/json', 'unsupported_version', '/swagger'],
        'remote reference' => [json_encode(openApiDocumentWith(overrides: ['components' => ['schemas' => ['Order' => ['$ref' => 'https://example.com/order.json']]]])), 'application/json', 'external_ref', '/components/schemas/Order/$ref'],
        'reference cycle' => [json_encode(openApiDocumentWith(overrides: ['components' => ['schemas' => ['Order' => ['$ref' => '#/components/schemas/Order']]]])), 'application/json', 'ref_cycle', '/components/schemas/Order/$ref'],
        'YAML above 512 KB' => ["openapi: 3.1.0\n# ".str_repeat('a', 512 * 1024), 'application/yaml', 'limit_exceeded', ''],
        'malformed YAML' => ["openapi: 3.1.0\ninfo: [".IMPORT_CONTENT_MARKER, 'application/yaml', 'malformed', ''],
        'YAML not UTF-8' => ["openapi: \"\xFF\"\n", 'application/yaml', 'malformed', ''],
        // Where a silent null would leave the document valid (FIND-040).
        'YAML PHP constant tag' => ["openapi: 3.1.0\ninfo: {title: Orders, version: '1'}\npaths: {}\nx-leak: !php/const PHP_VERSION\n", 'application/yaml', 'malformed', ''],
        'YAML PHP object tag' => ["openapi: 3.1.0\ninfo: {title: Orders, version: '1'}\npaths: {}\nx-object: !php/object 'O:8:\"stdClass\":0:{}'\n", 'application/yaml', 'malformed', ''],
        'YAML PHP enum tag' => ["openapi: 3.1.0\ninfo: {title: Orders, version: '1'}\npaths: {}\nx-enum: !php/enum Foo::Bar\n", 'application/yaml', 'malformed', ''],
        'JSON nested above 64 levels' => [str_repeat('[', 70).str_repeat(']', 70), 'application/json', 'malformed', ''],
        'relative file reference' => [json_encode(openApiDocumentWith(overrides: ['components' => ['schemas' => ['Order' => ['$ref' => 'order.yaml#/Order']]]])), 'application/json', 'external_ref', '/components/schemas/Order/$ref'],
        'file URL reference' => [json_encode(openApiDocumentWith(overrides: ['components' => ['schemas' => ['Order' => ['$ref' => 'file:///etc/passwd']]]])), 'application/json', 'external_ref', '/components/schemas/Order/$ref'],
        'protocol-relative reference' => [json_encode(openApiDocumentWith(overrides: ['components' => ['schemas' => ['Order' => ['$ref' => '//attacker.test/order.json']]]])), 'application/json', 'external_ref', '/components/schemas/Order/$ref'],
        'title above 255 characters' => [json_encode(openApiDocumentWith(overrides: ['info' => ['title' => str_repeat('t', 256), 'version' => '1']])), 'application/json', 'limit_exceeded', '/info/title'],
        'info version above 100 characters' => [json_encode(openApiDocumentWith(overrides: ['info' => ['title' => 'Orders', 'version' => str_repeat('9', 101)]])), 'application/json', 'limit_exceeded', '/info/version'],
        'OpenAPI patch number out of range' => [json_encode(openApiDocumentWith(overrides: ['openapi' => '3.1.'.str_repeat('9', 30)])), 'application/json', 'unsupported_version', '/openapi'],
    ]);

    it('rejects other media types with 415, without auditing', function (string $contentType) {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $auditCount = AuditLog::count();

        importInto($specification, $owner, json_encode(openApiDocumentWith()), $contentType)->assertStatus(415);

        expect(AuditLog::count())->toBe($auditCount)
            ->and(ApiSpecificationVersion::count())->toBe(0);
    })->with(['text/plain', 'application/xml', 'application/x-www-form-urlencoded', 'application/jsonx']);

    it('rejects a body above 2 MB with 413, without auditing', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $auditCount = AuditLog::count();

        importInto($specification, $owner, '{}', server: ['CONTENT_LENGTH' => 2 * 1024 * 1024 + 1])->assertStatus(413);
        importInto($specification, $owner, str_repeat(' ', 2 * 1024 * 1024 + 1))->assertStatus(413);

        expect(AuditLog::count())->toBe($auditCount);
    });

    it('answers 429 above the quota before looking at the body size', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        foreach (range(1, 10) as $attempt) {
            importInto($specification, $owner, '{')->assertUnprocessable();
        }

        importInto($specification, $owner, '{}', server: ['CONTENT_LENGTH' => 3 * 1024 * 1024])->assertTooManyRequests();
    });

    it('answers 401 to an anonymous import before looking at its body size', function () {
        $specification = importableSpecification(User::factory()->create());

        importInto($specification, null, '{}', server: ['CONTENT_LENGTH' => 3 * 1024 * 1024])->assertUnauthorized();
    });

    it('requires a token with the write ability', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        app('auth')->forgetGuards();

        test()->call('POST', versionsUrl($specification), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.tokenFor($owner, ['read']),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(openApiDocumentWith()))->assertForbidden();

        expect(ApiSpecificationVersion::count())->toBe(0);
    });

    it('refuses a second concurrent import in the organization with 409, without auditing', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $sibling = ApiSpecification::factory()->for($specification->application)->create();
        $running = Cache::lock('api-imports:organization:'.$specification->organization_id, 30);
        $running->get();
        $auditCount = AuditLog::count();

        importInto($sibling, $owner, json_encode(openApiDocumentWith()))
            ->assertConflict()
            ->assertJsonPath('message', 'Another import is in progress in this organization. Try again shortly.');

        $running->release();
        expect(AuditLog::count())->toBe($auditCount);
        importInto($sibling, $owner, json_encode(openApiDocumentWith()))->assertCreated();
    });

    it('refuses an import under an archived specification, application or project with 409', function (string $level) {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $target = match ($level) {
            'specification' => $specification,
            'application' => $specification->application,
            'project' => $specification->application->project,
        };
        // archived_at is deliberately not fillable; set it as the Actions do.
        $target->forceFill(['archived_at' => now()])->save();

        importInto($specification, $owner, json_encode(openApiDocumentWith()))->assertConflict();

        expect(ApiSpecificationVersion::count())->toBe(0);
    })->with(['specification', 'application', 'project']);

    it('returns 404 to users without an active membership in the organization', function (string $actor) {
        $specification = importableSpecification(User::factory()->create());
        $user = User::factory()->create();
        match ($actor) {
            'outsider' => null,
            'member of another organization' => importableSpecification($user),
            'disabled member' => OrganizationMembership::factory()->disabled()
                ->for($specification->organization)->for($user)->create(['role' => OrganizationRole::Owner]),
        };

        importInto($specification, $user, json_encode(openApiDocumentWith()))->assertNotFound();

        expect(ApiSpecificationVersion::count())->toBe(0);
    })->with(['outsider', 'member of another organization', 'disabled member']);
});

describe('Import quotas', function () {
    it('limits each user to 10 import attempts per hour, refused ones included', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);

        foreach (range(1, 10) as $attempt) {
            importInto($specification, $owner, '{')->assertUnprocessable();
        }

        importInto($specification, $owner, json_encode(openApiDocumentWith()))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
        expect(ApiSpecificationVersion::count())->toBe(0)
            ->and(AuditLog::where('action', 'api_specification_import_rejected')->count())->toBe(10);

        $this->travel(61)->minutes();
        importInto($specification, $owner, json_encode(openApiDocumentWith()))->assertCreated();
    });

    it('limits the organization to 30 attempts per hour, spent only by those who may import there', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $members = User::factory()->count(3)->create()->each(fn (User $member) => OrganizationMembership::factory()
            ->for($specification->organization)->for($member)->create(['role' => OrganizationRole::Member]));

        // Someone outside the organization cannot drain its quota.
        $outsider = User::factory()->create();
        foreach (range(1, 10) as $attempt) {
            importInto($specification, $outsider, '{')->assertNotFound();
        }

        foreach ($members as $member) {
            foreach (range(1, 10) as $attempt) {
                importInto($specification, $member, '{')->assertUnprocessable();
            }
        }

        // The owner has used none of their own 10.
        importInto($specification, $owner, json_encode(openApiDocumentWith()))->assertTooManyRequests();
        expect(ApiSpecificationVersion::count())->toBe(0);
    });
});

describe('Listing and showing versions', function () {
    it('lists the versions newest first, with their endpoint counts', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        importInto($specification, $owner, json_encode(openApiDocumentWith([['get', '/a']])))->assertCreated();
        importInto($specification, $owner, json_encode(openApiDocumentWith([['get', '/a'], ['get', '/b']])))->assertCreated();
        ApiSpecificationVersion::factory()->create();

        asToken($owner, 'GET', versionsUrl($specification))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('data.0.endpoints_count', 2)
            ->assertJsonPath('data.1.number', 1)
            ->assertJsonPath('data.1.endpoints_count', 1)
            ->assertJsonMissingPath('data.0.raw_document');
    });

    it('shows a version only under its own specification', function () {
        $owner = User::factory()->create();
        $specification = importableSpecification($owner);
        $sibling = ApiSpecification::factory()->for($specification->application)->create();
        $id = importInto($specification, $owner, json_encode(openApiDocumentWith()))->assertCreated()->json('data.id');

        asToken($owner, 'GET', versionsUrl($specification, "/{$id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.endpoints_count', 2);
        asToken($owner, 'GET', versionsUrl($sibling, "/{$id}"))->assertNotFound();
    });
});
