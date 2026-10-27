<?php

use App\Models\Application;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertApplicationRow(array $attributes): void
{
    // Savepoint, so a rejected statement does not abort the test transaction.
    DB::transaction(fn () => DB::table('applications')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Direct insert',
        'slug' => 'direct-insert',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]));
}

describe('Application integrity in PostgreSQL', function () {
    it('rejects an application of one organization pointing to a project of another', function () {
        $project = Project::factory()->create();
        $otherOrganizationProject = Project::factory()->create();

        expect(fn () => insertApplicationRow([
            'organization_id' => $project->organization_id,
            'project_id' => $otherOrganizationProject->id,
        ]))->toThrow(QueryException::class, 'applications_organization_id_project_id_foreign');

        expect(Application::count())->toBe(0);
    });

    it('rejects an application without a project', function () {
        $project = Project::factory()->create();

        expect(fn () => insertApplicationRow([
            'organization_id' => $project->organization_id,
            'project_id' => null,
        ]))->toThrow(QueryException::class);

        expect(Application::count())->toBe(0);
    });

    it('keeps an application bound to its organization and project', function (string $column) {
        $application = Application::factory()->create();
        $replacement = $column === 'organization_id'
            ? Project::factory()->create()->organization_id
            : Project::factory()->create(['organization_id' => $application->organization_id])->id;

        expect(fn () => DB::transaction(fn () => DB::table('applications')
            ->where('id', $application->id)
            ->update([$column => $replacement])))
            ->toThrow(QueryException::class, "applications.{$column} is immutable");

        expect($application->fresh()->{$column})->toBe($application->{$column});
    })->with(['organization_id', 'project_id']);

    it('keeps slugs unique per project only', function () {
        $application = Application::factory()->create(['slug' => 'checkout']);
        $sibling = Project::factory()->create(['organization_id' => $application->organization_id]);
        Application::factory()->for($sibling)->create(['slug' => 'checkout']);

        expect(fn () => insertApplicationRow([
            'organization_id' => $application->organization_id,
            'project_id' => $application->project_id,
            'slug' => 'checkout',
        ]))->toThrow(QueryException::class, 'applications_organization_id_project_id_slug_unique');

        expect(Application::where('slug', 'checkout')->count())->toBe(2);
    });
});
