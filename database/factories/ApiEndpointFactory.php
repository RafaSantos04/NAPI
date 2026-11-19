<?php

namespace Database\Factories;

use App\Models\ApiEndpoint;
use App\Models\ApiSpecificationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiEndpoint>
 */
class ApiEndpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_specification_version_id' => ApiSpecificationVersion::factory(),
            // Same organization as the version (composite FK).
            'organization_id' => fn (array $attributes) => ApiSpecificationVersion::findOrFail($attributes['api_specification_version_id'])->organization_id,
            'method' => 'GET',
            'path' => '/'.fake()->unique()->slug(2),
            'operation_id' => fake()->unique()->lexify('operation??????'),
            'summary' => fake()->sentence(),
        ];
    }
}
