<?php

use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function insertProjectRow(array $attributes): void
{
    // Savepoint, so a rejected statement does not abort the test transaction.
    DB::transaction(fn () => DB::table('projects')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'Direct insert',
        'slug' => 'direct-insert',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]));
}

describe('Project integrity in PostgreSQL', function () {
    it('rejects a project whose creator belongs to another organization', function () {
        $organization = organizationFor(User::factory()->create());
        $foreignMembership = OrganizationMembership::factory()->create();

        expect(fn () => insertProjectRow([
            'organization_id' => $organization->id,
            'created_by_membership_id' => $foreignMembership->id,
        ]))->toThrow(QueryException::class, 'projects_organization_id_created_by_membership_id_foreign');

        expect(Project::count())->toBe(0);
    });

    it('rejects a project without a creator', function () {
        $organization = organizationFor(User::factory()->create());

        expect(fn () => insertProjectRow([
            'organization_id' => $organization->id,
            'created_by_membership_id' => null,
        ]))->toThrow(QueryException::class);

        expect(Project::count())->toBe(0);
    });

    it('keeps a project bound to its organization and creator', function (string $column) {
        $project = Project::factory()->create();
        $otherMembership = OrganizationMembership::factory()->create(
            $column === 'organization_id' ? [] : ['organization_id' => $project->organization_id],
        );
        $replacement = $column === 'organization_id' ? $otherMembership->organization_id : $otherMembership->id;

        expect(fn () => DB::transaction(fn () => DB::table('projects')
            ->where('id', $project->id)
            ->update([$column => $replacement])))
            ->toThrow(QueryException::class, "projects.{$column} is immutable");

        expect($project->fresh()->{$column})->toBe($project->{$column});
    })->with(['organization_id', 'created_by_membership_id']);

    it('keeps slugs unique per organization only', function () {
        $project = Project::factory()->create(['slug' => 'billing']);
        $otherOrganizationProject = Project::factory()->create(['slug' => 'billing']);

        expect(fn () => insertProjectRow([
            'organization_id' => $project->organization_id,
            'created_by_membership_id' => $project->created_by_membership_id,
            'slug' => 'billing',
        ]))->toThrow(QueryException::class, 'projects_organization_id_slug_unique');

        expect($otherOrganizationProject->organization_id)->not->toBe($project->organization_id)
            ->and(Project::where('slug', 'billing')->count())->toBe(2);
    });
});
