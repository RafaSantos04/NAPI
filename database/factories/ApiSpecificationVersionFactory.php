<?php

namespace Database\Factories;

use App\Models\ApiSpecification;
use App\Models\ApiSpecificationVersion;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiSpecificationVersion>
 */
class ApiSpecificationVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_specification_id' => ApiSpecification::factory(),
            // Same organization as the specification and as the importer
            // (composite FKs).
            'organization_id' => fn (array $attributes) => ApiSpecification::findOrFail($attributes['api_specification_id'])->organization_id,
            'imported_by_membership_id' => fn (array $attributes) => OrganizationMembership::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'number' => fn (array $attributes) => (int) ApiSpecificationVersion::query()
                ->where('api_specification_id', $attributes['api_specification_id'])
                ->max('number') + 1,
            'format' => 'json',
            'openapi_version' => '3.1.0',
            'info_title' => fake()->words(3, true),
            'info_version' => '1.0.0',
            'raw_document' => fn (array $attributes) => json_encode([
                'openapi' => $attributes['openapi_version'],
                'info' => ['title' => $attributes['info_title'], 'version' => $attributes['info_version']],
                'paths' => ['/'.fake()->unique()->slug(1) => ['get' => ['responses' => ['200' => ['description' => 'OK']]]]],
            ], JSON_THROW_ON_ERROR),
            'content_sha256' => fn (array $attributes) => hash('sha256', $attributes['raw_document']),
            'size_bytes' => fn (array $attributes) => strlen($attributes['raw_document']),
        ];
    }
}
