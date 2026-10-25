<?php

namespace Database\Factories;

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => OrganizationRole::Owner]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => OrganizationMembershipStatus::Disabled]);
    }
}
