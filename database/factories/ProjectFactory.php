<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // The creator must belong to the project's organization (composite FK).
            'created_by_membership_id' => fn (array $attributes) => OrganizationMembership::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => null,
        ];
    }

    public function createdBy(OrganizationMembership $membership): static
    {
        return $this->state(fn () => [
            'organization_id' => $membership->organization_id,
            'created_by_membership_id' => $membership->id,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
