<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDetails>
 */
class UserDetailsFactory extends Factory
{
    protected $model = UserDetails::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cpf_hash' => UserDetails::hashCpf($this->fakeCpf()),
            'phone' => fake()->numerify('11#########'),
            'cep' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => fake()->buildingNumber(),
            'neighborhood' => fake()->city(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'data_consent_at' => now(),
        ];
    }

    /**
     * Attach the details to a specific user.
     */
    public function withUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Generate a unique, structurally valid Brazilian CPF (digits only).
     *
     * Uses 9 unique random digits as the base (guaranteeing the full 11-digit
     * number is unique across the run) and computes the two real check digits,
     * so factory data looks and validates like a genuine CPF.
     */
    private function fakeCpf(): string
    {
        $base = fake()->unique()->numerify('#########');
        $digits = array_map('intval', str_split($base));

        $digits[] = $this->cpfCheckDigit($digits, 10);
        $digits[] = $this->cpfCheckDigit($digits, 11);

        return implode('', $digits);
    }

    /**
     * @param  array<int, int>  $digits
     */
    private function cpfCheckDigit(array $digits, int $firstWeight): int
    {
        $sum = 0;

        foreach ($digits as $digit) {
            $sum += $digit * $firstWeight;
            $firstWeight--;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
