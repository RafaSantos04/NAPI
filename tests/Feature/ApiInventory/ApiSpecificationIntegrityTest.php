<?php

use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\Application;
use App\Models\OrganizationMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertApiSpecificationRow(array $attributes): void
{
    // Savepoint, so a rejected statement does not abort the test transaction.
    DB::transaction(fn () => DB::table('api_specifications')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Direct insert',
        'slug' => 'direct-insert',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]));
}

/**
 * A valid version row of $specification, with $attributes on top.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertApiSpecificationVersionRow(ApiSpecification $specification, array $attributes = []): void
{
    $document = '{"openapi":"3.1.0"}';

    DB::transaction(fn () => DB::table('api_specification_versions')->insert([
        'id' => (string) Str::ulid(),
        'organization_id' => $specification->organization_id,
        'api_specification_id' => $specification->id,
        'imported_by_membership_id' => OrganizationMembership::factory()
            ->create(['organization_id' => $specification->organization_id])->id,
        'number' => 1,
        'format' => 'json',
        'openapi_version' => '3.1.0',
        'info_title' => 'Direct insert',
        'info_version' => '1.0.0',
        'content_sha256' => hash('sha256', $document),
        'size_bytes' => strlen($document),
        'raw_document' => $document,
        ...$attributes,
    ]));
}

describe('API specification integrity in PostgreSQL', function () {
    it('rejects a specification of one organization pointing to an application of another', function () {
        $application = Application::factory()->create();
        $foreignApplication = Application::factory()->create();

        expect(fn () => insertApiSpecificationRow([
            'organization_id' => $application->organization_id,
            'application_id' => $foreignApplication->id,
        ]))->toThrow(QueryException::class, 'api_specifications_organization_id_application_id_foreign');

        expect(ApiSpecification::count())->toBe(0);
    });

    it('keeps a specification bound to its organization and application', function (string $column) {
        $specification = ApiSpecification::factory()->create();
        $replacement = $column === 'organization_id'
            ? Application::factory()->create()->organization_id
            : Application::factory()->for($specification->application->project)->create()->id;

        expect(fn () => DB::transaction(fn () => DB::table('api_specifications')
            ->where('id', $specification->id)
            ->update([$column => $replacement])))
            ->toThrow(QueryException::class, "api_specifications.{$column} is immutable");

        expect($specification->fresh()->{$column})->toBe($specification->{$column});
    })->with(['organization_id', 'application_id']);

    it('keeps slugs unique per application only', function () {
        $specification = ApiSpecification::factory()->create(['slug' => 'public-api']);
        ApiSpecification::factory()
            ->for(Application::factory()->for($specification->application->project))
            ->create(['slug' => 'public-api']);

        expect(fn () => insertApiSpecificationRow([
            'organization_id' => $specification->organization_id,
            'application_id' => $specification->application_id,
            'slug' => 'public-api',
        ]))->toThrow(QueryException::class, 'api_specifications_organization_id_application_id_slug_unique');

        expect(ApiSpecification::where('slug', 'public-api')->count())->toBe(2);
    });
});

describe('API specification version integrity in PostgreSQL', function () {
    it('never updates or deletes a version, not even by direct SQL', function (string $statement) {
        $version = ApiSpecificationVersion::factory()->create();
        $original = DB::table('api_specification_versions')->where('id', $version->id)->first();

        expect(fn () => DB::transaction(fn () => match ($statement) {
            'update the document' => DB::table('api_specification_versions')->where('id', $version->id)->update(['raw_document' => '{}']),
            'update the number' => DB::table('api_specification_versions')->where('id', $version->id)->update(['number' => 99]),
            'delete' => DB::table('api_specification_versions')->where('id', $version->id)->delete(),
        }))->toThrow(QueryException::class, 'api_specification_versions is append-only');

        expect(DB::table('api_specification_versions')->where('id', $version->id)->first())->toEqual($original);
    })->with(['update the document', 'update the number', 'delete']);

    it('rejects a version whose specification or importer belongs to another organization', function (string $column, string $constraint) {
        $specification = ApiSpecification::factory()->create();
        $foreign = match ($column) {
            'api_specification_id' => ApiSpecification::factory()->create()->id,
            'imported_by_membership_id' => OrganizationMembership::factory()->create()->id,
        };

        expect(fn () => insertApiSpecificationVersionRow($specification, [$column => $foreign]))
            ->toThrow(QueryException::class, $constraint);

        expect(ApiSpecificationVersion::count())->toBe(0);
    })->with([
        'specification' => ['api_specification_id', 'api_specification_versions_specification_foreign'],
        'importer' => ['imported_by_membership_id', 'api_specification_versions_importer_foreign'],
    ]);

    it('rejects a version with values outside its domain', function (array $attributes, string $constraint) {
        $specification = ApiSpecification::factory()->create();

        expect(fn () => insertApiSpecificationVersionRow($specification, $attributes))
            ->toThrow(QueryException::class, $constraint);

        expect(ApiSpecificationVersion::count())->toBe(0);
    })->with([
        'unknown format' => [['format' => 'xml'], 'api_specification_versions_format_check'],
        'number zero' => [['number' => 0], 'api_specification_versions_number_check'],
        'empty document size' => [['size_bytes' => 0], 'api_specification_versions_size_check'],
        'hash not in lowercase hex' => [['content_sha256' => str_repeat('Z', 64)], 'api_specification_versions_sha256_check'],
    ]);

    it('keeps version numbers and contents unique per specification', function (string $column, string $constraint) {
        $version = ApiSpecificationVersion::factory()->create();
        $attributes = match ($column) {
            'number' => ['number' => $version->number],
            'content_sha256' => ['number' => $version->number + 1, 'content_sha256' => $version->content_sha256],
        };

        expect(fn () => insertApiSpecificationVersionRow($version->specification, $attributes))
            ->toThrow(QueryException::class, $constraint);

        expect(ApiSpecificationVersion::count())->toBe(1);
    })->with([
        'number' => ['number', 'api_specification_versions_number_unique'],
        'content' => ['content_sha256', 'api_specification_versions_content_unique'],
    ]);

    it('numbers versions per specification and keeps the document out of serialization', function () {
        $specification = ApiSpecification::factory()->create();
        $first = ApiSpecificationVersion::factory()->for($specification, 'specification')->create();
        $second = ApiSpecificationVersion::factory()->for($specification, 'specification')->create();
        $other = ApiSpecificationVersion::factory()->create();

        expect([$first->number, $second->number, $other->number])->toBe([1, 2, 1])
            ->and($first->toArray())->not->toHaveKey('raw_document')
            ->and($first->fresh()->raw_document)->toStartWith('{"openapi":"3.1.0"');
    });
});
