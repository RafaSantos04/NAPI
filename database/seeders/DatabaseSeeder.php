<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // System profiles and menus belong to every environment; the demo
        // users only to local and testing (UserSeeder refuses the rest).
        $this->call(array_values(array_filter([
            ProfileSeeder::class,
            MenuSeeder::class,
            app()->environment(UserSeeder::ENVIRONMENTS) ? UserSeeder::class : null,
            SecurityLabSeeder::class,
        ])));
    }
}
