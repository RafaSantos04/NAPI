<?php

namespace Database\Factories;

use App\Models\ApiSpecification;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiSpecification>
 */
class ApiSpecificationFactory extends Factory
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
            // Same organization as the application (composite FK).
            'organization_id' => fn (array $attributes) => Application::findOrFail($attributes['application_id'])->organization_id,
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
