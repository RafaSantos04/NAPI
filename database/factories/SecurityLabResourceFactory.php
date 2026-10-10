<?php

namespace Database\Factories;

use App\Models\SecurityLabResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityLabResource>
 */
class SecurityLabResourceFactory extends Factory
{
    protected $model = SecurityLabResource::class;

    /**
     * The owner is a lab persona: an inactive account that cannot sign in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory()->inactive(),
            'name' => 'Documento '.fake()->unique()->bothify('??-###'),
            'content' => 'Synthetic Security Lab Data — '.fake()->sentence(),
        ];
    }
}
