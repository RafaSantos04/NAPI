<?php

use App\Domain\ApiInventory\OpenApiParser;
use App\Models\ApiEndpoint;
use App\Models\ApiSpecificationVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A valid endpoint row of $version, with $attributes on top.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertApiEndpointRow(ApiSpecificationVersion $version, array $attributes = []): void
{
    // Savepoint, so a rejected statement does not abort the test transaction.
    DB::transaction(fn () => DB::table('api_endpoints')->insert([
        'id' => (string) Str::ulid(),
        'organization_id' => $version->organization_id,
        'api_specification_version_id' => $version->id,
        'method' => 'GET',
        'path' => '/orders/{id}',
        ...$attributes,
    ]));
}

describe('API endpoint integrity in PostgreSQL', function () {
    it('rejects an endpoint of one organization pointing to a version of another', function () {
        $version = ApiSpecificationVersion::factory()->create();
        $foreignVersion = ApiSpecificationVersion::factory()->create();

        expect(fn () => insertApiEndpointRow($version, ['api_specification_version_id' => $foreignVersion->id]))
            ->toThrow(QueryException::class, 'api_endpoints_version_foreign');

        expect(ApiEndpoint::count())->toBe(0);
    });

    it('never updates or deletes an endpoint, not even by direct SQL', function (string $statement) {
        $endpoint = ApiEndpoint::factory()->create();
        $original = DB::table('api_endpoints')->where('id', $endpoint->id)->first();

        expect(fn () => DB::transaction(fn () => match ($statement) {
            'update the path' => DB::table('api_endpoints')->where('id', $endpoint->id)->update(['path' => '/changed']),
            'move it to another version' => DB::table('api_endpoints')->where('id', $endpoint->id)->update([
                'api_specification_version_id' => ApiSpecificationVersion::factory()
                    ->for($endpoint->version->specification, 'specification')
                    ->create()->id,
            ]),
            'delete' => DB::table('api_endpoints')->where('id', $endpoint->id)->delete(),
        }))->toThrow(QueryException::class, 'api_endpoints is append-only');

        expect(DB::table('api_endpoints')->where('id', $endpoint->id)->first())->toEqual($original);
    })->with(['update the path', 'move it to another version', 'delete']);

    it('rejects an endpoint with values outside its domain', function (array $attributes, string $constraint) {
        $version = ApiSpecificationVersion::factory()->create();

        expect(fn () => insertApiEndpointRow($version, $attributes))
            ->toThrow(QueryException::class, $constraint);

        expect(ApiEndpoint::count())->toBe(0);
    })->with([
        'unknown method' => [['method' => 'CONNECT'], 'api_endpoints_method_check'],
        'lowercase method' => [['method' => 'get'], 'api_endpoints_method_check'],
        'path without leading slash' => [['path' => 'orders'], 'api_endpoints_path_check'],
    ]);

    it('identifies an endpoint by method and path template within its version', function () {
        $version = ApiSpecificationVersion::factory()->create();
        insertApiEndpointRow($version, ['path' => '/orders/{id}']);

        expect(fn () => insertApiEndpointRow($version, ['path' => '/orders/{orderId}']))
            ->toThrow(QueryException::class, 'api_endpoints_identity_unique');

        // Another method, another path or another version is another endpoint.
        insertApiEndpointRow($version, ['method' => 'DELETE', 'path' => '/orders/{orderId}']);
        insertApiEndpointRow($version, ['path' => '/orders/{id}/items']);
        insertApiEndpointRow(ApiSpecificationVersion::factory()->for($version->specification, 'specification')->create());

        expect(ApiEndpoint::count())->toBe(4);
    });

    it('stores every operation the parser projects, with the values it read', function () {
        $document = (new OpenApiParser)->parse(json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Orders', 'version' => '1'],
            'paths' => [
                '/orders/{id}' => [
                    'get' => ['operationId' => 'showOrder', 'summary' => str_repeat('Long summary. ', 100), 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/orders/{id}/items' => ['trace' => ['responses' => ['200' => ['description' => 'OK']]]],
            ],
        ], JSON_THROW_ON_ERROR), 'json');
        $version = ApiSpecificationVersion::factory()->create();

        foreach ($document->endpoints as $endpoint) {
            ApiEndpoint::factory()->for($version, 'version')->create($endpoint);
        }

        expect($version->endpoints()->orderBy('path')->orderBy('method')->get(['method', 'path', 'operation_id', 'summary'])->toArray())
            ->toBe([
                ['method' => 'DELETE', 'path' => '/orders/{id}', 'operation_id' => null, 'summary' => null],
                ['method' => 'GET', 'path' => '/orders/{id}', 'operation_id' => 'showOrder', 'summary' => str_repeat('Long summary. ', 100)],
                ['method' => 'TRACE', 'path' => '/orders/{id}/items', 'operation_id' => null, 'summary' => null],
            ])
            ->and($version->endpoints()->first()->organization_id)->toBe($version->organization_id);
    });
});
