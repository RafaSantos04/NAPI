<?php

namespace Database\Factories;

use App\Domain\Projects\Enums\EnvironmentClassification;
use App\Models\Application;
use App\Models\Environment;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            // Same organization as the application and as the responsible
            // membership (composite FKs).
            'organization_id' => fn (array $attributes) => Application::findOrFail($attributes['application_id'])->organization_id,
            'responsible_membership_id' => fn (array $attributes) => OrganizationMembership::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'classification' => EnvironmentClassification::Staging,
            'authorized_scope' => 'staging.example.test, read-only accounts',
            'authorization_reference' => null,
        ];
    }

    public function responsible(OrganizationMembership $membership): static
    {
        return $this->state(fn () => ['responsible_membership_id' => $membership->id]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
