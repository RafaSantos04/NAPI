<?php

use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\ApiEndpoint;
use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Application;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Str;

// P3-T05: the endpoint inventory of one version, read-only.

/**
 * A version in a fresh organization owned by $owner, with endpoints given as
 * [method, path, operationId] triples.
 *
 * @param  list<array{0: string, 1: string, 2?: string}>  $endpoints
 */
function inventoryVersionOf(User $owner, array $endpoints): ApiSpecificationVersion
{
    $organization = organizationFor($owner);
    $specification = ApiSpecification::factory()
        ->for(Application::factory()->for(projectCreatedBy($owner, $organization)))
        ->create();
    $version = ApiSpecificationVersion::factory()->for($specification, 'specification')->create();

    foreach ($endpoints as $endpoint) {
        ApiEndpoint::factory()->for($version, 'version')->create([
            'method' => $endpoint[0],
            'path' => $endpoint[1],
            'operation_id' => $endpoint[2] ?? null,
        ]);
    }

    return $version;
}

function endpointsUrl(ApiSpecificationVersion $version, string $query = '', ?ApiSpecification $specification = null): string
{
    $specification ??= $version->specification;

    return "/api/v1/organizations/{$specification->organization_id}/api-specifications/{$specification->id}/versions/{$version->id}/endpoints{$query}";
}

describe('Listing the endpoint inventory', function () {
    it('lists the endpoints of the version by path and method, 100 per page', function () {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, [['POST', '/orders', 'createOrder'], ['GET', '/orders', 'listOrders'], ['GET', '/accounts']]);
        // Another version of the same specification keeps its own inventory.
        ApiEndpoint::factory()->for(ApiSpecificationVersion::factory()->for($version->specification, 'specification'), 'version')
            ->create(['path' => '/other']);

        asToken($owner, 'GET', endpointsUrl($version), abilities: ['read'])
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('data.*.path', ['/accounts', '/orders', '/orders'])
            ->assertJsonPath('data.*.method', ['GET', 'GET', 'POST'])
            ->assertJsonPath('data.1.operation_id', 'listOrders')
            ->assertJsonPath('data.1.api_specification_version_id', $version->id)
            ->assertJsonPath('data.1.organization_id', $version->organization_id);
    });

    it('pages a 2,000-operation inventory in 20 requests', function () {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, []);
        ApiEndpoint::query()->insert(array_map(fn (int $index) => [
            'id' => (string) Str::ulid(),
            'organization_id' => $version->organization_id,
            'api_specification_version_id' => $version->id,
            'method' => 'GET',
            'path' => sprintf('/resources/%04d', $index),
        ], range(1, 2000)));

        asToken($owner, 'GET', endpointsUrl($version, '?page=20'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2000)
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('data.99.path', '/resources/2000');
    });

    it('filters by method, in any case', function (string $method) {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, [['GET', '/orders'], ['DELETE', '/orders/{id}'], ['GET', '/accounts']]);

        asToken($owner, 'GET', endpointsUrl($version, "?method={$method}"))
            ->assertOk()
            ->assertJsonPath('data.*.path', ['/orders/{id}']);
    })->with(['DELETE', 'delete']);

    it('searches the path and the operationId, matching wildcards literally', function (string $search, array $paths) {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, [
            ['GET', '/orders/{id}', 'showOrder'],
            ['GET', '/accounts', 'listAccounts'],
            ['GET', '/stock_items'],
            ['GET', '/stockXitems'],
        ]);

        asToken($owner, 'GET', endpointsUrl($version, '?search='.urlencode($search)))
            ->assertOk()
            ->assertJsonPath('data.*.path', $paths);
    })->with([
        'path' => ['orders', ['/orders/{id}']],
        'operationId, ignoring case' => ['LISTACC', ['/accounts']],
        'underscore is literal' => ['stock_', ['/stock_items']],
        'percent is literal' => ['%', []],
    ]);

    it('rejects an unknown method or a search above 100 characters', function (string $query, string $field) {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, [['GET', '/orders']]);

        asToken($owner, 'GET', endpointsUrl($version, $query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'method' => ['?method=CONNECT', 'method'],
        'search' => ['?search='.str_repeat('a', 101), 'search'],
    ]);

    it('lets a member read the inventory of an archived specification', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $version = inventoryVersionOf($owner, [['GET', '/orders']]);
        OrganizationMembership::factory()->for($version->organization)->for($member)->create(['role' => OrganizationRole::Member]);
        $version->specification->forceFill(['archived_at' => now()])->save();

        asToken($member, 'GET', endpointsUrl($version))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('finds a version only under its own specification', function () {
        $owner = User::factory()->create();
        $version = inventoryVersionOf($owner, [['GET', '/orders']]);
        $sibling = ApiSpecification::factory()->for($version->specification->application)->create();

        asToken($owner, 'GET', endpointsUrl($version, specification: $sibling))->assertNotFound();
    });
});
