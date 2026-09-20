<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'route_name' => fake()->unique()->slug(3),
            'icon' => 'menu',
            'order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Attach the menu to a parent menu.
     */
    public function withParent(Menu $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
