<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\OrganizationMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertEnvironmentRow(array $attributes): void
{
    // Savepoint, so a rejected statement does not abort the test transaction.
    DB::transaction(fn () => DB::table('environments')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Direct insert',
        'slug' => 'direct-insert',
        'classification' => 'staging',
        'authorized_scope' => 'staging.example.test',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]));
}

/**
 * Valid row attributes for an environment under the application.
 *
 * @return array<string, string>
 */
function environmentRowFor(Application $application): array
{
    return [
        'organization_id' => $application->organization_id,
        'application_id' => $application->id,
        'responsible_membership_id' => OrganizationMembership::factory()
            ->create(['organization_id' => $application->organization_id])
            ->id,
    ];
}

describe('Environment integrity in PostgreSQL', function () {
    it('rejects an environment pointing to an application of another organization', function () {
        $application = Application::factory()->create();

        expect(fn () => insertEnvironmentRow([
            ...environmentRowFor($application),
            'application_id' => Application::factory()->create()->id,
        ]))->toThrow(QueryException::class, 'environments_organization_id_application_id_foreign');

        expect(Environment::count())->toBe(0);
    });

    it('rejects a responsible membership of another organization', function () {
        $application = Application::factory()->create();

        expect(fn () => insertEnvironmentRow([
            ...environmentRowFor($application),
            'responsible_membership_id' => OrganizationMembership::factory()->create()->id,
        ]))->toThrow(QueryException::class, 'environments_organization_id_responsible_membership_id_foreign');

        expect(Environment::count())->toBe(0);
    });

    it('rejects an environment without its required references or with an unknown classification', function (string $column, ?string $value) {
        $application = Application::factory()->create();

        expect(fn () => insertEnvironmentRow([
            ...environmentRowFor($application),
            $column => $value,
        ]))->toThrow(QueryException::class);

        expect(Environment::count())->toBe(0);
    })->with([
        'no application' => ['application_id', null],
        'no responsible' => ['responsible_membership_id', null],
        'no authorized scope' => ['authorized_scope', null],
        'unknown classification' => ['classification', 'qa'],
    ]);

    it('keeps an environment bound to its organization and application', function (string $column) {
        $environment = Environment::factory()->create();
        $replacement = $column === 'organization_id'
            ? Application::factory()->create()->organization_id
            : Application::factory()->create(['project_id' => $environment->application->project_id])->id;

        expect(fn () => DB::transaction(fn () => DB::table('environments')
            ->where('id', $environment->id)
            ->update([$column => $replacement])))
            ->toThrow(QueryException::class, "environments.{$column} is immutable");

        expect($environment->fresh()->{$column})->toBe($environment->{$column});
    })->with(['organization_id', 'application_id']);

    it('lets the responsible membership change within the organization', function () {
        $environment = Environment::factory()->create();
        $other = OrganizationMembership::factory()->create(['organization_id' => $environment->organization_id]);

        DB::table('environments')->where('id', $environment->id)->update(['responsible_membership_id' => $other->id]);

        expect($environment->fresh()->responsible_membership_id)->toBe($other->id);
    });

    it('keeps slugs unique per application only', function () {
        $environment = Environment::factory()->create(['slug' => 'staging']);
        $sibling = Application::factory()->create(['project_id' => $environment->application->project_id]);
        Environment::factory()->for($sibling)->create(['slug' => 'staging']);

        expect(fn () => insertEnvironmentRow([
            ...environmentRowFor($environment->application),
            'slug' => 'staging',
        ]))->toThrow(QueryException::class, 'environments_organization_id_application_id_slug_unique');

        expect(Environment::where('slug', 'staging')->count())->toBe(2);
    });
});
